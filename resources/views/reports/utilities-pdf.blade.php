<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Utilities Report</title>
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
            color: #1e293b;
            margin: 20px;
        }

        h1 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #0f172a;
        }

        .subtitle {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: left;
            padding: 8px;
            border-bottom: 2px solid #cbd5e1;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background-color: #f8fafc;
            border-top: 2px solid #cbd5e1;
        }
    </style>
</head>
<body>
    <h1>{{ __('Utilities Report') }}</h1>
    <div class="subtitle">
        {{ $propertyName }} &nbsp;&middot;&nbsp; 
        {{ __('Date From') }}: {{ $dateFrom }} &nbsp;&middot;&nbsp; 
        {{ __('Date Until') }}: {{ $dateUntil }}
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Room') }}</th>
                <th>{{ __('Utility') }}</th>
                <th>{{ __('Date') }}</th>
                <th class="text-right">{{ __('Previous') }}</th>
                <th class="text-right">{{ __('Current') }}</th>
                <th class="text-right">{{ __('Used') }}</th>
                <th class="text-right">{{ __('Cost') }}</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalUsed = 0;
                $totalCost = 0;
            @endphp
            @forelse($records as $record)
                @php
                    $uom = $record->propertyUtility?->unit_of_measure;
                    $cost = $record->propertyUtility?->rate
                        ? round((float) $record->amount_used * (float) $record->propertyUtility->rate, 2)
                        : 0.0;
                    $totalUsed += (float) $record->amount_used;
                    $totalCost += $cost;
                @endphp
                <tr>
                    <td>{{ $record->unit?->room_number ?? '—' }}</td>
                    <td>{{ $record->propertyUtility?->name ?? '—' }}</td>
                    <td>{{ $record->reading_date?->format('Y-m-d') ?? '—' }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format((float) $record->old_reading, 3), '0'), '.') }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format((float) $record->new_reading, 3), '0'), '.') }}</td>
                    <td class="text-right">
                        {{ rtrim(rtrim(number_format((float) $record->amount_used, 3), '0'), '.') }}
                        @if($uom) <span style="font-size: 9px; color: #64748b;">{{ $uom }}</span> @endif
                    </td>
                    <td class="text-right">${{ number_format($cost, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #64748b;">{{ __('No reading') }}</td>
                </tr>
            @endforelse

            @if($records->isNotEmpty())
                <tr class="total-row">
                    <td colspan="5">{{ __('Total') }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($totalUsed, 3), '0'), '.') }}</td>
                    <td class="text-right">${{ number_format($totalCost, 2) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
