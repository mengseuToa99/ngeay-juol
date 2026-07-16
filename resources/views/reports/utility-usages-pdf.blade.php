<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Utility Usage Export') }}</title>
    <style>
        @font-face {
            font-family: 'NotoSansKhmer';
            font-style: normal;
            font-weight: normal;
            src: url('{{ resource_path('fonts/NotoSansKhmer-Regular.ttf') }}') format('truetype');
        }
        @font-face {
            font-family: 'NotoSansKhmer';
            font-style: normal;
            font-weight: bold;
            src: url('{{ resource_path('fonts/NotoSansKhmer-Bold.ttf') }}') format('truetype');
        }

        body {
            font-family: 'NotoSansKhmer', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }
        h1, h2 {
            margin-bottom: 5px;
            color: #111;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .summary-section {
            margin-bottom: 25px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .summary-table th, .summary-table td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            text-align: left;
        }
        .summary-table th {
            background-color: #f7fafc;
            font-weight: bold;
        }
        .usages-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .usages-table th, .usages-table td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            text-align: left;
        }
        .usages-table th {
            background-color: #f7fafc;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-active {
            background-color: #def7ec;
            color: #03543f;
        }
        .badge-waived {
            background-color: #fde8e8;
            color: #9b1c1c;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __('Utility Usage Export') }}</h1>
        <div><strong>{{ __('Property') }}:</strong> {{ $property->name }}</div>
        <div><strong>{{ __('Generated At') }}:</strong> {{ now()->format('Y-m-d H:i:s') }}</div>
        <div>
            <strong>{{ __('Filter Date') }}:</strong> 
            @if(($filters['time_period'] ?? 'all') === 'this_year')
                {{ __('This Year') }} ({{ now()->year }})
            @elseif(($filters['time_period'] ?? 'all') === 'last_year')
                {{ __('Last Year') }} ({{ now()->subYear()->year }})
            @elseif(($filters['time_period'] ?? 'all') === 'custom')
                {{ $filters['from_date'] ?? '—' }} {{ __('to') }} {{ $filters['until_date'] ?? '—' }}
            @else
                {{ __('All Time') }}
            @endif
        </div>
    </div>

    <div class="summary-section">
        <h2>{{ __('Monthly Cost Summary') }}</h2>
        <table class="summary-table">
            <thead>
                <tr>
                    @for ($m = 1; $m <= 12; $m++)
                        <th>{{ __(date('M', mktime(0, 0, 0, $m, 10))) }}</th>
                    @endfor
                    <th>{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    @for ($m = 1; $m <= 12; $m++)
                        <td>${{ number_format($monthsData[$m], 2) }}</td>
                    @endfor
                    <td><strong>${{ number_format($totalCost, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>{{ __('Detailed Usage Records') }}</h2>
    <table class="usages-table">
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Utility') }}</th>
                <th>{{ __('Unit') }}</th>
                <th>{{ __('Tenant Name') }}</th>
                <th class="text-right">{{ __('Previous') }}</th>
                <th class="text-right">{{ __('Current') }}</th>
                <th class="text-right">{{ __('Used') }}</th>
                <th class="text-right">{{ __('Rate') }}</th>
                <th class="text-right">{{ __('Amount Billed') }}</th>
                <th class="text-center">{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($usages as $usage)
                @php
                    $rate = (float) ($usage->propertyUtility?->rate ?? 0.0);
                    $amountBilled = $usage->is_waived ? 0.0 : ((float) $usage->amount_used * $rate);
                    $tenantName = $usage->rental?->occupant_name ?: ($usage->rental?->tenant?->name ?? '—');
                @endphp
                <tr>
                    <td>{{ $usage->reading_date ? $usage->reading_date->format('Y-m-d') : '—' }}</td>
                    <td>{{ $usage->propertyUtility?->name ?? '—' }}</td>
                    <td>{{ $usage->unit?->room_number ?? '—' }}</td>
                    <td>{{ $tenantName }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format((float) $usage->old_reading, 3), '0'), '.') }} {{ $usage->propertyUtility?->unit_of_measure }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format((float) $usage->new_reading, 3), '0'), '.') }} {{ $usage->propertyUtility?->unit_of_measure }}</td>
                    <td class="text-right"><strong>{{ rtrim(rtrim(number_format((float) $usage->amount_used, 3), '0'), '.') }} {{ $usage->propertyUtility?->unit_of_measure }}</strong></td>
                    <td class="text-right">${{ number_format($rate, 4) }}</td>
                    <td class="text-right">${{ number_format($amountBilled, 2) }}</td>
                    <td class="text-center">
                        @if($usage->is_waived)
                            <span class="badge badge-waived">{{ __('Waived') }}</span>
                        @else
                            <span class="badge badge-active">{{ __('Active') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">{{ __('No usage records found.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
