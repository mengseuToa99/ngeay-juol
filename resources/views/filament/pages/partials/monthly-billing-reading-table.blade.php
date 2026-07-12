<div class="rw-fast-entry-wrap">
    <table class="rw-fast-entry-table">
        <thead>
            <tr>
                <th>{{ __('Room') }}</th>
                <th>{{ __('Tenant') }}</th>
                <th class="rw-money-column">{{ __('Rent') }}</th>
                @foreach($this->activeUtilities() as $utility)
                    <th class="rw-fast-entry-utility-heading">
                        <span>{{ __($utility->name) }}</span>
                        <small>{{ __('Previous → New reading') }}</small>
                    </th>
                @endforeach
                <th class="rw-money-column">{{ __('Estimated total') }}</th>
                <th class="rw-action-column">{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($this->rooms as $index => $room)
                @php $summary = $this->roomSummary($index); @endphp
                <tr @class(['rw-fast-entry-row--skipped' => $room['skipped']])>
                    <td class="rw-fast-entry-room">
                        <span>{{ $room['room_number'] }}</span>
                    </td>
                    <td>
                        <div class="rw-fast-entry-tenant">{{ $room['occupant_name'] }}</div>
                    </td>
                    <td class="rw-money-column">
                        <strong>{{ $this->formatMoney($room['rent']) }}</strong>
                        @if($room['is_first_invoice'] ?? false)
                            <small>{{ __('Prorated') }}</small>
                        @endif
                    </td>

                    @foreach($room['utilities'] as $utilityIndex => $utility)
                        @php
                            $preview = $this->utilityPreview($index, $utilityIndex);
                            $state = $utility['state_override'] ?? 'normal';
                            $readingDisabled = $room['skipped'] || in_array($state, ['not_applicable', 'skipped_this_cycle'], true);
                        @endphp
                        <td class="rw-fast-entry-utility">
                            @if($utility['requires_reading'] ?? true)
                                <div class="rw-fast-reading">
                                    <div class="rw-fast-reading__previous">
                                        <span>{{ __('Previous') }}</span>
                                        <strong>{{ $preview['old_reading'] !== null ? $this->formatQuantity($preview['old_reading']) : '—' }}</strong>
                                    </div>
                                    <span class="rw-fast-reading__arrow">→</span>
                                    <label>
                                        <span class="sr-only">{{ __('New reading for :utility in room :room', ['utility' => $utility['utility_name'], 'room' => $room['room_number']]) }}</span>
                                        <input
                                            type="number"
                                            step="any"
                                            inputmode="decimal"
                                            wire:model.live.debounce.300ms="rooms.{{ $index }}.utilities.{{ $utilityIndex }}.new_reading"
                                            placeholder="{{ __('New reading') }}"
                                            {{ $readingDisabled ? 'disabled' : '' }}
                                        >
                                    </label>
                                </div>

                                @if($preview['is_lower_reading'])
                                    <input
                                        class="rw-fast-reading__reason"
                                        type="text"
                                        wire:model.live.debounce.300ms="rooms.{{ $index }}.utilities.{{ $utilityIndex }}.override_reason"
                                        placeholder="{{ __('Reason for lower reading') }}"
                                        {{ $readingDisabled ? 'disabled' : '' }}
                                    >
                                @endif

                                <div class="rw-fast-reading__result">
                                    @if($preview['amount_used'] !== null && ! $readingDisabled)
                                        <span>{{ $this->formatQuantity($preview['amount_used']) }} {{ $utility['unit_of_measure'] }}</span>
                                        <strong>{{ \App\Support\Money::format($preview['charge'], $utility['currency'] ?? 'USD') }}</strong>
                                    @elseif($readingDisabled)
                                        <span>{{ __('No reading required') }}</span>
                                    @else
                                        <span>{{ __('Waiting for reading') }}</span>
                                    @endif
                                </div>
                            @else
                                <div class="rw-fast-fixed-charge">
                                    <span>{{ __('Fixed') }}</span>
                                    <strong>{{ \App\Support\Money::format($preview['charge'], $utility['currency'] ?? 'USD') }}</strong>
                                </div>
                            @endif
                        </td>
                    @endforeach

                    <td class="rw-money-column rw-fast-entry-total">
                        <strong>{{ $summary['estimated_total_display'] }}</strong>
                    </td>
                    <td class="rw-action-column">
                        <button
                            type="button"
                            wire:click="toggleRoomSkip({{ $index }})"
                            @class(['rw-fast-skip', 'rw-fast-skip--restore' => $room['skipped']])
                        >
                            {{ $room['skipped'] ? __('Restore') : __('Skip') }}
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
