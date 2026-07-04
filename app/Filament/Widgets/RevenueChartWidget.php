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
        return __('Revenue & Cash Flow');
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

        $monthlyPaid = array_fill(1, 12, 0.0);
        $monthlyExpected = array_fill(1, 12, 0.0);

        foreach ($invoices as $invoice) {
            $month = $invoice->issue_date ? $invoice->issue_date->month : null;
            if (! $month) {
                continue;
            }

            $monthlyPaid[$month] += (float) $invoice->amount_paid;
            $monthlyExpected[$month] += (float) $invoice->amount_due;
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
                    'label' => __('Revenue') . ' ($)',
                    'data' => array_values($monthlyPaid),
                    'borderColor' => '#10b981', // green/emerald for collected cash
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => __('Expected Revenue') . ' ($)',
                    'data' => array_values($monthlyExpected),
                    'borderColor' => '#6366f1', // indigo for expected
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $monthLabels,
        ];
    }
}
