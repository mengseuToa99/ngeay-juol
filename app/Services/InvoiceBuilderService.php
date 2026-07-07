<?php

namespace App\Services;

use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PropertySetting;
use App\Models\Rental;
use App\Models\UtilityUsage;
use App\Services\ProratingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single, centralized invoice-creation path — replaces the old app's five
 * near-identical, copy-pasted invoice builders. Every invoice (rent-only, with
 * utilities, ad-hoc) flows through here, so a data-integrity change is made once.
 */
class InvoiceBuilderService
{
    /**
     * Generate a unique, human-readable invoice number: INV-{landlordId}-{YYYYMM}-{seq}.
     */
    public function generateNumber(int $landlordId, Carbon $period): string
    {
        $prefix = sprintf('INV-%d-%s-', $landlordId, $period->format('Ym'));

        $seq = Invoice::withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->count() + 1;

        do {
            $candidate = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $exists = Invoice::withoutGlobalScopes()->where('invoice_number', $candidate)->exists();
            $seq++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Build and persist an invoice for a rental.
     *
     * @param  array{
     *     rental?: Rental, rental_id?: int,
     *     period_start: Carbon|string, period_end: Carbon|string,
     *     issue_date?: Carbon|string, due_date?: Carbon|string,
     *     include_rent?: bool,
     *     is_first_invoice?: bool,
     *     usages?: array<int, UtilityUsage|int>,
     *     adhoc?: array<int, array{description: string, amount: float|string}>,
     *     status?: InvoiceStatus, notes?: string|null
     * }  $data
     */
    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $rental = $data['rental'] ?? Rental::withoutGlobalScopes()->findOrFail($data['rental_id']);
            $propertySetting = PropertySetting::where('property_id', $rental->property_id)->first();
            $dueDays = $propertySetting?->invoice_due_days ?? 7;

            $periodStart = Carbon::parse($data['period_start']);
            $periodEnd = Carbon::parse($data['period_end']);
            $issueDate = isset($data['issue_date']) ? Carbon::parse($data['issue_date']) : Carbon::now();
            $dueDate = isset($data['due_date']) ? Carbon::parse($data['due_date']) : $issueDate->copy()->addDays($dueDays);

            // 1. Collect all currencies to see if we have mixed currencies and check fallback
            $rentCurrency = \App\Support\Money::normalize($rental->monthly_rent_currency ?: 'USD');
            $depositCurrency = \App\Support\Money::normalize($rental->security_deposit_currency ?: $rentCurrency);
            
            $currencies = [];
            if ($data['include_rent'] ?? true) {
                $currencies[] = $rentCurrency;
                if ($data['is_first_invoice'] ?? false) {
                    $currencies[] = $depositCurrency;
                }
            }
            foreach ($data['usages'] ?? [] as $usage) {
                $usageModel = $usage instanceof UtilityUsage ? $usage : UtilityUsage::withoutGlobalScopes()->findOrFail($usage);
                $currencies[] = \App\Support\Money::normalize($usageModel->propertyUtility?->currency ?: 'USD');
            }
            foreach ($data['adhoc'] ?? [] as $line) {
                $currencies[] = \App\Support\Money::normalize($line['currency'] ?? ($propertySetting?->currency ?? 'USD'));
            }

            $currencies = array_unique($currencies);
            $isMixed = count($currencies) > 1;

            // 2. Resolve exchange rate
            $rate = null;
            $rateSource = null;
            $rateDate = null;
            $rateFetchedAt = null;
            $rateNote = null;

            if ($propertySetting && $propertySetting->exchange_rate_source === 'manual' && $propertySetting->usd_khr_exchange_rate > 0) {
                $rate = (float) $propertySetting->usd_khr_exchange_rate;
                $rateSource = 'manual';
                $rateDate = $propertySetting->exchange_rate_date ? Carbon::parse($propertySetting->exchange_rate_date)->toDateString() : now()->toDateString();
                $rateFetchedAt = $propertySetting->exchange_rate_fetched_at ?? now();
                $rateNote = 'Manual rate configured by landlord';
            } else {
                try {
                    $exchangeRateService = app(\App\Services\ExchangeRateService::class);
                    $nbc = $exchangeRateService->fetchUsdToKhr();
                    $rate = (float) $nbc['rate'];
                    $rateSource = 'NBC';
                    $rateDate = $nbc['date'];
                    $rateFetchedAt = now();
                    $rateNote = 'Official NBC rate';
                } catch (\Throwable $e) {
                    // Fallback: use last saved NBC rate if available
                    if ($propertySetting && $propertySetting->usd_khr_exchange_rate > 0) {
                        $rate = (float) $propertySetting->usd_khr_exchange_rate;
                        $rateSource = $propertySetting->exchange_rate_source ?: 'fallback';
                        $rateDate = $propertySetting->exchange_rate_date ? Carbon::parse($propertySetting->exchange_rate_date)->toDateString() : null;
                        $rateFetchedAt = $propertySetting->exchange_rate_fetched_at;
                        $rateNote = 'Fallback: NBC official fetch failed, using last saved rate (' . ($propertySetting->exchange_rate_source ?: 'NBC') . ')';
                    }
                }
            }

            if ($isMixed && $rate === null) {
                throw new \RuntimeException('Cannot build mixed-currency invoice because no exchange rate is available (NBC fetch failed and no manual/saved fallback rate exists).');
            }

            $invoice = new Invoice([
                'rental_id' => $rental->id,
                'invoice_number' => $this->generateNumber($rental->landlord_id, $periodStart),
                'amount_due' => 0,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'payment_status' => ($data['status'] ?? InvoiceStatus::Pending),
                'notes' => $data['notes'] ?? null,
                
                // exchange rate snapshot fields
                'usd_khr_rate' => $rate,
                'exchange_rate_source' => $rateSource,
                'exchange_rate_date' => $rateDate,
                'exchange_rate_fetched_at' => $rateFetchedAt,
                'exchange_rate_note' => $rateNote,
            ]);
            $invoice->landlord_id = $rental->landlord_id;
            $invoice->tenant_id = $rental->tenant_id;
            $invoice->save();

            $totalUsd = 0.0;
            $totalKhr = 0.0;
            $nativeUsd = 0.0;
            $nativeKhr = 0.0;

            $lineRate = $rate ?: 4000.0; // fallback conversion rate if single currency and rate is null

            // Rent line
            if ($data['include_rent'] ?? true) {
                $isFirstInvoice = (bool) ($data['is_first_invoice'] ?? false);
                $rentCurrency = $rental->monthly_rent_currency ?: 'USD';
                $rentBaseAmount = (float) $rental->monthly_rent;
                $rentDescription = 'Monthly rent';

                if ($isFirstInvoice) {
                    $rentBaseAmount = ProratingService::compute(
                        $propertySetting,
                        (float) $rental->monthly_rent,
                        $periodStart,
                        $periodEnd,
                        $rentCurrency
                    );

                    // Build a human-readable description that reflects the mode.
                    $mode = $propertySetting?->first_month_billing_mode;
                    $rentDescription = $mode?->getLabel()
                        ? 'First month rent (' . $mode->getLabel() . ')'
                        : 'First month rent';

                    // Add a security deposit line if configured.
                    $depositAmount = ProratingService::depositAmount($propertySetting, (float) $rental->monthly_rent, $depositCurrency);
                    if ($depositAmount > 0) {
                        $depositUsd = \App\Support\Money::convert($depositAmount, $depositCurrency, 'USD', $lineRate);
                        $depositKhr = \App\Support\Money::convert($depositAmount, $depositCurrency, 'KHR', $lineRate);
                        $depositUnitPriceUsd = \App\Support\Money::convert($depositAmount, $depositCurrency, 'USD', $lineRate, 4);
                        $depositUnitPriceKhr = \App\Support\Money::convert($depositAmount, $depositCurrency, 'KHR', $lineRate, 4);

                        $invoice->lines()->create([
                            'line_type'   => InvoiceLineType::AdHoc,
                            'description' => 'Security deposit (' . ($propertySetting->upfront_deposit_months ?? 0) . '× monthly rent)',
                            'quantity'    => 1,
                            'unit_price'  => $depositAmount,
                            'amount'      => $depositAmount,
                            'currency'    => $depositCurrency,
                            'unit_price_currency' => $depositCurrency,
                            'amount_usd'  => $depositUsd,
                            'amount_khr'  => $depositKhr,
                            'unit_price_usd' => $depositUnitPriceUsd,
                            'unit_price_khr' => $depositUnitPriceKhr,
                            'exchange_rate' => $rate,
                        ]);

                        $totalUsd += $depositUsd;
                        $totalKhr += $depositKhr;
                        if ($depositCurrency === 'USD') {
                            $nativeUsd += $depositAmount;
                        } else {
                            $nativeKhr += $depositAmount;
                        }
                    }
                }

                $rentOverride = $data['rent_override'] ?? [];
                $rentState = $rentOverride['state'] ?? 'normal';
                $rentReason = $rentOverride['reason'] ?? null;
                $rentUnitPrice = $rentBaseAmount;
                $rentAmount = $rentBaseAmount;
                $rentIsWaived = false;
                $rentStateLabel = null;

                if ($rentState === 'not_applicable' || $rentState === 'skipped_this_cycle') {
                    \App\Models\BillingRunChargeDecision::create([
                        'billing_run_id' => $data['billing_run_id'] ?? null,
                        'rental_id' => $rental->id,
                        'unit_id' => $rental->unit_id,
                        'resolved_state' => $rentState,
                        'source_scope_type' => 'manual',
                        'reason' => $rentReason ?? 'Rent excluded this cycle',
                        'amount' => $rentBaseAmount,
                        'currency' => $rentCurrency,
                    ]);
                } else {
                    if ($rentState === 'free') {
                        $rentAmount = 0.0;
                        $rentStateLabel = 'Free';
                        $rentDescription .= ' (' . __('Free') . ')';
                    } elseif ($rentState === 'waived') {
                        $rentAmount = 0.0;
                        $rentIsWaived = true;
                        $rentStateLabel = 'Waived' . ($rentReason ? ': ' . $rentReason : '');
                        $rentDescription .= ' (' . __('Waived') . ($rentReason ? ': ' . $rentReason : '') . ')';
                    } elseif ($rentState === 'custom') {
                        if (isset($rentOverride['amount'])) {
                            $rentAmount = (float) $rentOverride['amount'];
                        }
                        if (isset($rentOverride['currency'])) {
                            $rentCurrency = $rentOverride['currency'];
                        }
                        $rentUnitPrice = $rentAmount;
                    }

                    $rentUsd = \App\Support\Money::convert($rentAmount, $rentCurrency, 'USD', $lineRate);
                    $rentKhr = \App\Support\Money::convert($rentAmount, $rentCurrency, 'KHR', $lineRate);
                    $rentUnitPriceUsd = \App\Support\Money::convert($rentUnitPrice, $rentCurrency, 'USD', $lineRate, 4);
                    $rentUnitPriceKhr = \App\Support\Money::convert($rentUnitPrice, $rentCurrency, 'KHR', $lineRate, 4);

                    $invoice->lines()->create([
                        'line_type'   => InvoiceLineType::Rent,
                        'description' => $rentDescription,
                        'quantity'    => 1,
                        'unit_price'  => $rentUnitPrice,
                        'amount'      => $rentAmount,
                        'is_waived'   => $rentIsWaived,
                        'currency'    => $rentCurrency,
                        'unit_price_currency' => $rentCurrency,
                        'amount_usd'  => $rentUsd,
                        'amount_khr'  => $rentKhr,
                        'unit_price_usd' => $rentUnitPriceUsd,
                        'unit_price_khr' => $rentUnitPriceKhr,
                        'exchange_rate' => $rate,
                        'charge_state' => $rentState,
                        'charge_state_label' => $rentStateLabel,
                        'charge_state_reason' => $rentReason,
                    ]);

                    $totalUsd += $rentUsd;
                    $totalKhr += $rentKhr;
                    if ($rentCurrency === 'USD') {
                        $nativeUsd += $rentAmount;
                    } else {
                        $nativeKhr += $rentAmount;
                    }
                }
            }

            // Utility lines (priced + waiver-resolved by UtilityBillingService)
            foreach ($data['usages'] ?? [] as $usage) {
                $usage = $usage instanceof UtilityUsage
                    ? $usage
                    : UtilityUsage::withoutGlobalScopes()->findOrFail($usage);

                $override = $data['utility_overrides'][$usage->property_utility_id] ?? [];
                $manualParams = [];
                if (isset($override['state']) && $override['state'] !== 'normal') {
                    $manualParams['manual_state'] = $override['state'];
                    $manualParams['manual_reason'] = $override['reason'] ?? null;
                    if (isset($override['amount'])) {
                        $manualParams['manual_amount'] = (float) $override['amount'];
                    }
                    if (isset($override['currency'])) {
                        $manualParams['manual_currency'] = $override['currency'];
                    }
                }

                $charge = UtilityBillingService::resolveCharge($usage, $manualParams);
                $utilityCurrency = \App\Support\Money::normalize($charge['currency'] ?? 'USD');
                
                $chargeAmount = (float) $charge['amount'];
                $chargeRate = (float) $charge['rate'];

                if (isset($charge['should_create_line']) && !$charge['should_create_line']) {
                    // Record billing run charge decision for audit
                    \App\Models\BillingRunChargeDecision::create([
                        'billing_run_id' => $data['billing_run_id'] ?? null,
                        'rental_id' => $rental->id,
                        'unit_id' => $rental->unit_id,
                        'property_utility_id' => $usage->property_utility_id,
                        'charge_definition_id' => $usage->propertyUtility?->charge_definition_id,
                        'resolved_state' => $charge['effective_state'],
                        'source_scope_type' => $charge['source_scope_type'] ?? 'rule',
                        'source_scope_id' => $charge['charge_rule_id'] ?? null,
                        'reason' => $charge['reason'] ?? null,
                        'amount' => $chargeRate * $charge['quantity'],
                        'currency' => $utilityCurrency,
                    ]);
                    continue;
                }

                $chargeUsd = \App\Support\Money::convert($chargeAmount, $utilityCurrency, 'USD', $lineRate);
                $chargeKhr = \App\Support\Money::convert($chargeAmount, $utilityCurrency, 'KHR', $lineRate);
                $chargeUnitPriceUsd = \App\Support\Money::convert($chargeRate, $utilityCurrency, 'USD', $lineRate, 4);
                $chargeUnitPriceKhr = \App\Support\Money::convert($chargeRate, $utilityCurrency, 'KHR', $lineRate, 4);

                $description = trim(($usage->propertyUtility?->name ?? 'Utility').' usage');
                if (!empty($charge['tenant_facing_label'])) {
                    $description .= ' (' . $charge['tenant_facing_label'] . ')';
                }

                $invoice->lines()->create([
                    'line_type' => InvoiceLineType::Utility,
                    'utility_usage_id' => $usage->id,
                    'description' => $description,
                    'quantity' => $charge['quantity'],
                    'unit_price' => $charge['rate'],
                    'amount' => $charge['amount'],
                    'is_waived' => $charge['is_waived'],
                    'currency' => $utilityCurrency,
                    'unit_price_currency' => $utilityCurrency,
                    'amount_usd'  => $chargeUsd,
                    'amount_khr'  => $chargeKhr,
                    'unit_price_usd' => $chargeUnitPriceUsd,
                    'unit_price_khr' => $chargeUnitPriceKhr,
                    'exchange_rate' => $rate,
                    
                    // snapshot fields
                    'charge_state' => $charge['effective_state'] ?? 'normal',
                    'charge_state_label' => $charge['tenant_facing_label'] ?? null,
                    'charge_state_reason' => $charge['reason'] ?? null,
                    'charge_definition_id' => $usage->propertyUtility?->charge_definition_id,
                    'charge_rule_id' => $charge['charge_rule_id'] ?? null,
                ]);
                
                $totalUsd += $chargeUsd;
                $totalKhr += $chargeKhr;
                if ($utilityCurrency === 'USD') {
                    $nativeUsd += $chargeAmount;
                } else {
                    $nativeKhr += $chargeAmount;
                }
            }

            // Ad-hoc lines
            foreach ($data['adhoc'] ?? [] as $line) {
                $amount = (float) $line['amount'];
                $adhocCurrency = \App\Support\Money::normalize($line['currency'] ?? ($propertySetting?->currency ?? 'USD'));

                $adhocUsd = \App\Support\Money::convert($amount, $adhocCurrency, 'USD', $lineRate);
                $adhocKhr = \App\Support\Money::convert($amount, $adhocCurrency, 'KHR', $lineRate);
                $adhocUnitPriceUsd = \App\Support\Money::convert($amount, $adhocCurrency, 'USD', $lineRate, 4);
                $adhocUnitPriceKhr = \App\Support\Money::convert($amount, $adhocCurrency, 'KHR', $lineRate, 4);

                $invoice->lines()->create([
                    'line_type' => InvoiceLineType::AdHoc,
                    'description' => $line['description'],
                    'amount' => $amount,
                    'currency' => $adhocCurrency,
                    'unit_price_currency' => $adhocCurrency,
                    'amount_usd'  => $adhocUsd,
                    'amount_khr'  => $adhocKhr,
                    'unit_price_usd' => $adhocUnitPriceUsd,
                    'unit_price_khr' => $adhocUnitPriceKhr,
                    'exchange_rate' => $rate,
                ]);
                
                $totalUsd += $adhocUsd;
                $totalKhr += $adhocKhr;
                if ($adhocCurrency === 'USD') {
                    $nativeUsd += $amount;
                } else {
                    $nativeKhr += $amount;
                }
            }

            $invoice->total_usd = $totalUsd;
            $invoice->total_khr = $totalKhr;
            $invoice->native_usd_total = $nativeUsd;
            $invoice->native_khr_total = $nativeKhr;

            $reportingCurrency = \App\Support\Money::normalize($propertySetting?->currency ?? 'USD');
            if ($reportingCurrency === 'KHR') {
                $invoice->amount_due = $totalKhr;
            } else {
                $invoice->amount_due = $totalUsd;
            }

            $invoice->payment_status = $invoice->resolvePaymentStatus();
            $invoice->save();

            return $invoice->refresh();
        });
    }
}
