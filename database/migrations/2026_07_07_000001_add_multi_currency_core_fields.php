<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('rent_currency', 3)->default('USD')->after('rent_amount');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->string('monthly_rent_currency', 3)->default('USD')->after('monthly_rent');
            $table->string('security_deposit_currency', 3)->default('USD')->after('security_deposit');
        });

        Schema::table('property_utilities', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('rate');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('usd_khr_rate', 12, 4)->nullable()->after('amount_paid');
            $table->string('exchange_rate_source', 64)->nullable()->after('usd_khr_rate');
            $table->date('exchange_rate_date')->nullable()->after('exchange_rate_source');
            $table->timestamp('exchange_rate_fetched_at')->nullable()->after('exchange_rate_date');
            $table->text('exchange_rate_note')->nullable()->after('exchange_rate_fetched_at');
            $table->decimal('total_usd', 12, 2)->nullable()->after('exchange_rate_note');
            $table->decimal('total_khr', 14, 0)->nullable()->after('total_usd');
            $table->decimal('native_usd_total', 12, 2)->nullable()->after('total_khr');
            $table->decimal('native_khr_total', 14, 0)->nullable()->after('native_usd_total');
            $table->decimal('paid_usd', 12, 2)->nullable()->after('native_khr_total');
            $table->decimal('paid_khr', 14, 0)->nullable()->after('paid_usd');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('amount');
            $table->string('unit_price_currency', 3)->nullable()->after('currency');
            $table->decimal('amount_usd', 12, 2)->nullable()->after('unit_price_currency');
            $table->decimal('amount_khr', 14, 0)->nullable()->after('amount_usd');
            $table->decimal('unit_price_usd', 12, 4)->nullable()->after('amount_khr');
            $table->decimal('unit_price_khr', 14, 4)->nullable()->after('unit_price_usd');
            $table->decimal('exchange_rate', 12, 4)->nullable()->after('unit_price_khr');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('amount');
            $table->decimal('amount_usd', 12, 2)->nullable()->after('currency');
            $table->decimal('amount_khr', 14, 0)->nullable()->after('amount_usd');
            $table->decimal('exchange_rate', 12, 4)->nullable()->after('amount_khr');
            $table->string('exchange_rate_source', 64)->nullable()->after('exchange_rate');
        });

        // Backfill logic
        $this->backfill();
    }

    private function backfill(): void
    {
        // 1. Units
        $units = DB::table('units')->get();
        foreach ($units as $unit) {
            $propSetting = DB::table('property_settings')->where('property_id', $unit->property_id)->first();
            $currency = $propSetting ? ($propSetting->currency ?? 'USD') : 'USD';
            if (!in_array($currency, ['USD', 'KHR'], true)) {
                $currency = 'USD';
            }
            DB::table('units')->where('id', $unit->id)->update(['rent_currency' => $currency]);
        }

        // 2. Rentals
        $rentals = DB::table('rentals')->get();
        foreach ($rentals as $rental) {
            $unit = DB::table('units')->where('id', $rental->unit_id)->first();
            $currency = $unit ? $unit->rent_currency : 'USD';
            DB::table('rentals')->where('id', $rental->id)->update([
                'monthly_rent_currency' => $currency,
                'security_deposit_currency' => $currency,
            ]);
        }

        // 3. Property Utilities
        $utilities = DB::table('property_utilities')->get();
        foreach ($utilities as $utility) {
            $propSetting = DB::table('property_settings')->where('property_id', $utility->property_id)->first();
            $currency = $propSetting ? ($propSetting->currency ?? 'USD') : 'USD';
            if (!in_array($currency, ['USD', 'KHR'], true)) {
                $currency = 'USD';
            }
            DB::table('property_utilities')->where('id', $utility->id)->update(['currency' => $currency]);
        }

        // 4. Invoices, Invoice Lines, Payments
        $invoices = DB::table('invoices')->get();
        foreach ($invoices as $invoice) {
            $rental = DB::table('rentals')->where('id', $invoice->rental_id)->first();
            $propertyId = $rental ? DB::table('units')->where('id', $rental->unit_id)->value('property_id') : null;
            $propSetting = $propertyId ? DB::table('property_settings')->where('property_id', $propertyId)->first() : null;

            $rate = $propSetting ? $propSetting->usd_khr_exchange_rate : null;
            $rateSource = $propSetting ? $propSetting->exchange_rate_source : null;
            $rateDate = $propSetting ? $propSetting->exchange_rate_date : null;
            $rateFetched = $propSetting ? $propSetting->exchange_rate_fetched_at : null;

            $currency = $propSetting ? ($propSetting->currency ?? 'USD') : 'USD';
            if (!in_array($currency, ['USD', 'KHR'], true)) {
                $currency = 'USD';
            }

            $totalUsd = null;
            $totalKhr = null;
            $nativeUsdTotal = null;
            $nativeKhrTotal = null;

            if ($currency === 'USD') {
                $totalUsd = $invoice->amount_due;
                $nativeUsdTotal = $invoice->amount_due;
                $nativeKhrTotal = 0;
                if ($rate) {
                    $totalKhr = round($totalUsd * $rate, 0);
                }
            } else {
                $totalKhr = $invoice->amount_due;
                $nativeKhrTotal = $invoice->amount_due;
                $nativeUsdTotal = 0;
                if ($rate && $rate > 0) {
                    $totalUsd = round($totalKhr / $rate, 2);
                }
            }

            $paidUsd = 0;
            $paidKhr = 0;
            if ($currency === 'USD') {
                $paidUsd = $invoice->amount_paid;
                if ($rate) {
                    $paidKhr = round($paidUsd * $rate, 0);
                }
            } else {
                $paidKhr = $invoice->amount_paid;
                if ($rate && $rate > 0) {
                    $paidUsd = round($paidKhr / $rate, 2);
                }
            }

            DB::table('invoices')->where('id', $invoice->id)->update([
                'usd_khr_rate' => $rate,
                'exchange_rate_source' => $rateSource,
                'exchange_rate_date' => $rateDate,
                'exchange_rate_fetched_at' => $rateFetched,
                'total_usd' => $totalUsd,
                'total_khr' => $totalKhr,
                'native_usd_total' => $nativeUsdTotal,
                'native_khr_total' => $nativeKhrTotal,
                'paid_usd' => $paidUsd,
                'paid_khr' => $paidKhr,
            ]);

            // Invoice Lines
            $lines = DB::table('invoice_lines')->where('invoice_id', $invoice->id)->get();
            foreach ($lines as $line) {
                $lineCurrency = $currency;
                $lineAmountUsd = null;
                $lineAmountKhr = null;
                $lineUnitPriceUsd = null;
                $lineUnitPriceKhr = null;

                if ($lineCurrency === 'USD') {
                    $lineAmountUsd = $line->amount;
                    $lineUnitPriceUsd = $line->unit_price;
                    if ($rate) {
                        $lineAmountKhr = round($lineAmountUsd * $rate, 0);
                        if ($lineUnitPriceUsd !== null) {
                            $lineUnitPriceKhr = round($lineUnitPriceUsd * $rate, 4);
                        }
                    }
                } else {
                    $lineAmountKhr = $line->amount;
                    $lineUnitPriceKhr = $line->unit_price;
                    if ($rate && $rate > 0) {
                        $lineAmountUsd = round($lineAmountKhr / $rate, 2);
                        if ($lineUnitPriceKhr !== null) {
                            $lineUnitPriceUsd = round($lineUnitPriceKhr / $rate, 4);
                        }
                    }
                }

                DB::table('invoice_lines')->where('id', $line->id)->update([
                    'currency' => $lineCurrency,
                    'unit_price_currency' => $lineCurrency,
                    'amount_usd' => $lineAmountUsd,
                    'amount_khr' => $lineAmountKhr,
                    'unit_price_usd' => $lineUnitPriceUsd,
                    'unit_price_khr' => $lineUnitPriceKhr,
                    'exchange_rate' => $rate,
                ]);
            }

            // Payments
            $payments = DB::table('payments')->where('invoice_id', $invoice->id)->get();
            foreach ($payments as $payment) {
                $paymentCurrency = $currency;
                $payAmountUsd = null;
                $payAmountKhr = null;

                if ($paymentCurrency === 'USD') {
                    $payAmountUsd = $payment->amount;
                    if ($rate) {
                        $payAmountKhr = round($payAmountUsd * $rate, 0);
                    }
                } else {
                    $payAmountKhr = $payment->amount;
                    if ($rate && $rate > 0) {
                        $payAmountUsd = round($payAmountKhr / $rate, 2);
                    }
                }

                DB::table('payments')->where('id', $payment->id)->update([
                    'currency' => $paymentCurrency,
                    'amount_usd' => $payAmountUsd,
                    'amount_khr' => $payAmountKhr,
                    'exchange_rate' => $rate,
                    'exchange_rate_source' => $rateSource,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'amount_usd',
                'amount_khr',
                'exchange_rate',
                'exchange_rate_source',
            ]);
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'unit_price_currency',
                'amount_usd',
                'amount_khr',
                'unit_price_usd',
                'unit_price_khr',
                'exchange_rate',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'usd_khr_rate',
                'exchange_rate_source',
                'exchange_rate_date',
                'exchange_rate_fetched_at',
                'exchange_rate_note',
                'total_usd',
                'total_khr',
                'native_usd_total',
                'native_khr_total',
                'paid_usd',
                'paid_khr',
            ]);
        });

        Schema::table('property_utilities', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_rent_currency',
                'security_deposit_currency',
            ]);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('rent_currency');
        });
    }
};
