<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\RentalStatus;
use App\Models\BillingRunChargeDecision;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Rental;
use App\Models\UtilityWaiver;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Portfolio aggregate. Auto-scoped by the models' LandlordScope: a landlord sees
 * their own totals; super_admin / support see the whole platform (the admin's
 * "global view" alongside per-property detail).
 */
class PortfolioStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -3;

    protected function getStats(): array
    {
        $invoices = Invoice::query()
            ->whereIn('payment_status', [
                InvoiceStatus::Pending->value,
                InvoiceStatus::Partial->value,
                InvoiceStatus::Overdue->value,
            ])
            ->get();

        $usdTotal = 0.0;
        $khrTotal = 0.0;

        foreach ($invoices as $invoice) {
            $usdTotal += $invoice->balance_usd;
            $khrTotal += $invoice->balance_khr;
        }

        $outstandingLabel = \App\Support\Money::format($usdTotal, 'USD') . ' / ' . \App\Support\Money::format($khrTotal, 'KHR');
        $hasOutstanding = ($usdTotal > 0.0 || $khrTotal > 0.0);
        $decisionCounts = BillingRunChargeDecision::query()
            ->selectRaw('resolved_state, COUNT(*) as count')
            ->groupBy('resolved_state')
            ->pluck('count', 'resolved_state');

        $decisionSummary = collect([
            __('Billed') . ': ' . (int) ($decisionCounts['normal'] ?? 0),
            __('Free') . ': ' . (int) ($decisionCounts['free'] ?? 0),
            __('Waived') . ': ' . (int) ($decisionCounts['waived'] ?? 0),
            __('Not applicable') . ': ' . (int) ($decisionCounts['not_applicable'] ?? 0),
            __('Skipped this cycle') . ': ' . (int) ($decisionCounts['skipped_this_cycle'] ?? 0),
            __('Custom adjustments') . ': ' . (int) ($decisionCounts['custom'] ?? 0),
        ])->implode(' · ');

        return [
            Stat::make(__('Properties'), Property::count())
                ->descriptionIcon('heroicon-o-building-office-2'),
            Stat::make(__('Active tenancies'), Rental::where('status', RentalStatus::Active->value)->count())
                ->descriptionIcon('heroicon-o-key'),
            Stat::make(__('Outstanding'), $outstandingLabel)
                ->description(__('unpaid invoice balances'))
                ->color($hasOutstanding ? 'warning' : 'success'),
            Stat::make(__('Charge decisions'), $decisionSummary)
                ->description(__('Billing run audit'))
                ->descriptionIcon('heroicon-o-clipboard-document-list'),
            Stat::make(__('Active waivers'), UtilityWaiver::where('waived', true)->count())
                ->descriptionIcon('heroicon-o-receipt-percent'),
        ];
    }
}
