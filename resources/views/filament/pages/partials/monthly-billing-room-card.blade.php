@php
    $summary = $this->roomSummary($index);
    $hasWarning = $this->roomHasInvalidPeriodOrDuplicate($index);
@endphp

<article
    wire:key="monthly-billing-room-{{ $room['rental_id'] ?? $index }}"
    @class([
        'rw-room-billing-card',
        'rw-room-billing-card--skipped' => $room['skipped'],
        'rw-room-billing-card--warning' => $hasWarning,
    ])
>
    <header class="rw-room-billing-card__header">
        <div class="rw-room-billing-card__identity">
            <span class="rw-room-billing-card__number">{{ $room['room_number'] }}</span>
            <div class="min-w-0">
                <h3>{{ $room['occupant_name'] }}</h3>
                <p>
                    {{ __('Monthly rent') }}:
                    <strong>{{ $this->formatMoney($room['rent']) }}</strong>
                    @if($room['is_first_invoice'] ?? false)
                        <span class="rw-room-billing-card__prorated">{{ __('Prorated') }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="rw-room-billing-card__total">
            <span>{{ __('Estimated total') }}</span>
            <strong>{{ $summary['estimated_total_display'] }}</strong>
        </div>
    </header>

    <div class="rw-room-billing-card__body">
        <section class="rw-room-period" aria-label="{{ __('Billing period') }}">
            <div class="rw-room-section-heading">
                <span class="rw-room-section-icon">
                    <x-heroicon-o-calendar-days class="h-4 w-4" />
                </span>
                <div>
                    <h4>{{ __('Billing period') }}</h4>
                    <p>{{ __('Choose the dates covered by this invoice.') }}</p>
                </div>
            </div>

            <div class="rw-room-period__fields">
                <label>
                    <span>{{ __('From') }}</span>
                    <input
                        type="date"
                        wire:model.live="rooms.{{ $index }}.period_start"
                        {{ $room['skipped'] ? 'disabled' : '' }}
                    >
                </label>
                <span class="rw-room-period__arrow">→</span>
                <label>
                    <span>{{ __('To') }}</span>
                    <input
                        type="date"
                        wire:model.live="rooms.{{ $index }}.period_end"
                        {{ $room['skipped'] ? 'disabled' : '' }}
                    >
                </label>
            </div>

            @if($hasWarning)
                <div class="rw-room-warning">
                    <x-heroicon-o-exclamation-triangle class="h-4 w-4 shrink-0" />
                    @if(\Carbon\Carbon::parse($room['period_start'])->isAfter(\Carbon\Carbon::parse($room['period_end'])))
                        <span>{{ __('Period start is after end') }}</span>
                    @elseif($this->hasDuplicateInvoice($index))
                        <span>{{ __('Duplicate invoice exists') }}</span>
                    @endif
                </div>
            @endif
        </section>

        <section class="rw-room-utilities" aria-label="{{ __('Utilities') }}">
            <div class="rw-room-section-heading">
                <span class="rw-room-section-icon">
                    <x-heroicon-o-bolt class="h-4 w-4" />
                </span>
                <div>
                    <h4>{{ __('Utility readings') }}</h4>
                    <p>{{ __('Enter the new meter value or change how this charge is handled.') }}</p>
                </div>
            </div>

            <div class="rw-room-utilities__grid">
                @forelse($room['utilities'] as $utilityIndex => $utility)
                    @php
                        $preview = $this->utilityPreview($index, $utilityIndex);
                        $state = $utility['state_override'] ?? 'normal';
                        $readingDisabled = $room['skipped'] || in_array($state, ['not_applicable', 'skipped_this_cycle'], true);
                    @endphp

                    <div class="rw-utility-panel">
                        <div class="rw-utility-panel__header">
                            <div>
                                <h5>{{ $utility['utility_name'] ?? __('Utility') }}</h5>
                                <p>{{ $utility['source_scope_label'] ?? __('Inherited from property') }}</p>
                            </div>
                            <span class="rw-utility-state">{{ \App\Services\ChargeRuleResolver::stateLabel((string) $state) }}</span>
                        </div>

                        <label class="rw-field">
                            <span>{{ __('Charge treatment') }}</span>
                            <select wire:model.live="rooms.{{ $index }}.utilities.{{ $utilityIndex }}.state_override" {{ $room['skipped'] ? 'disabled' : '' }}>
                                <option value="normal">{{ __('Normal') }}</option>
                                <option value="free">{{ __('Free') }}</option>
                                <option value="waived">{{ __('Waived') }}</option>
                                <option value="not_applicable">{{ __('Not applicable') }}</option>
                                <option value="skipped_this_cycle">{{ __('Skip this cycle') }}</option>
                                <option value="custom">{{ __('Custom amount') }}</option>
                            </select>
                        </label>

                        @if($state === 'custom')
                            <div class="rw-custom-charge">
                                <label class="rw-field">
                                    <span>{{ __('Amount') }}</span>
                                    <input type="number" step="any" wire:model.live="rooms.{{ $index }}.utilities.{{ $utilityIndex }}.override_amount">
                                </label>
                                <label class="rw-field">
                                    <span>{{ __('Currency') }}</span>
                                    <select wire:model.live="rooms.{{ $index }}.utilities.{{ $utilityIndex }}.override_currency">
                                        <option value="USD">USD</option>
                                        <option value="KHR">KHR</option>
                                    </select>
                                </label>
                            </div>
                        @endif

                        @if($utility['requires_reading'] ?? true)
                            <div class="rw-reading-entry">
                                <div class="rw-reading-entry__previous">
                                    <span>{{ __('Previous') }}</span>
                                    <strong>{{ $preview['old_reading'] !== null ? $this->formatQuantity($preview['old_reading']) : '—' }}</strong>
                                </div>
                                <span class="rw-reading-entry__arrow">→</span>
                                <label class="rw-field rw-reading-entry__new">
                                    <span>{{ __('New reading') }}</span>
                                    <input
                                        type="number"
                                        step="any"
                                        inputmode="decimal"
                                        wire:model.live.debounce.300ms="rooms.{{ $index }}.utilities.{{ $utilityIndex }}.new_reading"
                                        placeholder="0"
                                        {{ $readingDisabled ? 'disabled' : '' }}
                                    >
                                </label>
                            </div>

                            @if($preview['is_lower_reading'])
                                <label class="rw-field rw-lower-reading-reason">
                                    <span>{{ __('Reason for lower reading') }}</span>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="rooms.{{ $index }}.utilities.{{ $utilityIndex }}.override_reason"
                                        placeholder="{{ __('Add a short explanation') }}"
                                        {{ $readingDisabled ? 'disabled' : '' }}
                                    >
                                </label>
                            @endif

                            @if($preview['amount_used'] !== null && ! $readingDisabled)
                                <div class="rw-utility-result">
                                    <span>{{ $this->formatQuantity($preview['amount_used']) }} {{ $utility['unit_of_measure'] }}</span>
                                    <strong>{{ \App\Support\Money::format($preview['charge'], $utility['currency'] ?? 'USD') }}</strong>
                                </div>
                            @elseif($readingDisabled)
                                <div class="rw-utility-muted-result">{{ __('No reading required') }}</div>
                            @endif
                        @else
                            <div class="rw-utility-result">
                                <span>{{ __('Fixed charge') }}</span>
                                <strong>{{ \App\Support\Money::format($preview['charge'], $utility['currency'] ?? 'USD') }}</strong>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rw-no-utilities">{{ __('No utilities are configured for this room.') }}</div>
                @endforelse
            </div>
        </section>
    </div>

    <footer class="rw-room-billing-card__footer">
        <div class="rw-room-status">
            <span class="rw-room-status__dot"></span>
            <span>{{ $room['skipped'] ? __('This room will not be invoiced') : __('Included in this billing run') }}</span>
        </div>
        <button
            type="button"
            wire:click="toggleRoomSkip({{ $index }})"
            @class(['rw-room-skip', 'rw-room-skip--restore' => $room['skipped']])
        >
            {{ $room['skipped'] ? __('Restore room') : __('Skip this room') }}
        </button>
    </footer>
</article>
