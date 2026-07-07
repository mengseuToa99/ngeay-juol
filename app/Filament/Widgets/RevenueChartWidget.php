<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Support\ActiveProperty;
use Filament\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    protected static ?int $sort = -3; // position alongside PortfolioStats

    protected function getType(): string
    {
        return 'line';
    }

    public ?string $filter = null;

    public function getHeading(): string
    {
        return __('Revenue & Concessions');
    }

    protected function getFilters(): ?array
    {
        $currentYear = now()->year;
        $years = [];
        for ($year = $currentYear; $year >= $currentYear - 4; $year--) {
            $years[(string) $year] = (string) $year;
        }

        return $years;
    }

    protected function getData(): array
    {
        $year = (int) ($this->filter ?? now()->year);

        $query = Invoice::query()
            ->whereYear('issue_date', $year);

        $propertyId = ActiveProperty::id();
        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        }

        $invoices = $query->get();

        $reportingCurrency = \App\Support\Money::activeCurrency();
        $currencySymbol = \App\Support\Money::symbol($reportingCurrency);

        $monthlyRevenue = array_fill(1, 12, 0.0);
        $monthlyFreeValue = array_fill(1, 12, 0.0);
        $monthlyWaivedValue = array_fill(1, 12, 0.0);
        $monthlyCustomValue = array_fill(1, 12, 0.0);

        foreach ($invoices as $invoice) {
            $month = $invoice->issue_date ? $invoice->issue_date->month : null;
            if (! $month) {
                continue;
            }

            foreach ($invoice->lines->filter(fn ($line) => $line->shouldAppearOnTenantInvoice()) as $line) {
                $lineValue = (float) $line->amount;
                $concessionValue = (float) $line->unit_price * (float) $line->quantity;

                switch ($line->resolvedChargeState()) {
                    case 'free':
                        $monthlyFreeValue[$month] += $concessionValue;
                        break;
                    case 'waived':
                        $monthlyWaivedValue[$month] += $concessionValue;
                        break;
                    case 'custom':
                        $monthlyCustomValue[$month] += $lineValue;
                        $monthlyRevenue[$month] += $lineValue;
                        break;
                    default:
                        $monthlyRevenue[$month] += $lineValue;
                        break;
                }
            }
        }

        $monthLabels = [
            __('Jan'),
            __('Feb'),
            __('Mar'),
            __('Apr'),
            __('May'),
            __('Jun'),
            __('Jul'),
            __('Aug'),
            __('Sep'),
            __('Oct'),
            __('Nov'),
            __('Dec'),
        ];

        return [
            'datasets' => [
                [
                    'label' => __('Revenue') . ' (' . $currencySymbol . ')',
                    'data' => array_values($monthlyRevenue),
                    'borderColor' => '#10b981', // green/emerald for collected cash
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => __('Free value') . ' (' . $currencySymbol . ')',
                    'data' => array_values($monthlyFreeValue),
                    'borderColor' => '#0ea5e9', // sky for free concessions
                    'backgroundColor' => 'rgba(14, 165, 233, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => __('Waived value') . ' (' . $currencySymbol . ')',
                    'data' => array_values($monthlyWaivedValue),
                    'borderColor' => '#f59e0b', // amber for waived concessions
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => __('Custom adjustments') . ' (' . $currencySymbol . ')',
                    'data' => array_values($monthlyCustomValue),
                    'borderColor' => '#8b5cf6', // violet for custom overrides
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $monthLabels,
        ];
    }
}
