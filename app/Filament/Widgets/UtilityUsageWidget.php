<?php

namespace App\Filament\Widgets;

use App\Models\UtilityUsage;
use App\Support\ActiveProperty;
use Filament\Widgets\ChartWidget;

class UtilityUsageWidget extends ChartWidget
{
    protected static ?int $sort = -1;

    protected function getType(): string
    {
        return 'bar';
    }

    public ?string $filter = null;

    public function getHeading(): string
    {
        return __('Utility Usage');
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

        $query = UtilityUsage::query()
            ->with('propertyUtility')
            ->whereYear('reading_date', $year);

        $propertyId = ActiveProperty::id();
        if ($propertyId !== null) {
            $query->whereHas('unit', fn ($q) => $q->where('property_id', $propertyId));
        }

        $usages = $query->get();

        $dataByUtility = [];
        foreach ($usages as $usage) {
            $utilityName = $usage->propertyUtility?->name ?? 'Unknown';
            $month = $usage->reading_date ? $usage->reading_date->month : null;
            if (! $month) {
                continue;
            }

            if (! isset($dataByUtility[$utilityName])) {
                $dataByUtility[$utilityName] = array_fill(1, 12, 0.0);
            }

            $rate = (float) ($usage->propertyUtility?->rate ?? 0.0);
            $cost = $usage->is_waived ? 0.0 : (float) $usage->amount_used * $rate;
            $dataByUtility[$utilityName][$month] += round($cost, 2);
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

        $colors = [
            'electricity' => '#eab308', // Amber/Yellow
            'water' => '#3b82f6',       // Blue
            'gas' => '#f97316',         // Orange
        ];

        $datasets = [];
        $index = 0;
        foreach ($dataByUtility as $utilityName => $monthsData) {
            $key = strtolower($utilityName);
            $color = $colors[$key] ?? null;
            if (! $color) {
                $palette = ['#10b981', '#a855f7', '#ec4899', '#6366f1', '#14b8a6'];
                $color = $palette[$index % count($palette)];
                $index++;
            }

            $datasets[] = [
                'label' => __($utilityName) . ' ($)',
                'data' => array_values($monthsData),
                'backgroundColor' => $color,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $monthLabels,
        ];
    }
}
