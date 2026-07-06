<div class="rw-monthly-billing-root">
@if(! $this->embedded)
<x-filament-panels::page>
@endif
    @php
        $access = $this->getAccess();
        $selectedProperty = $this->selectedProperty();
        $rooms = $this->rooms;
    @endphp

    <div
        x-data="{
            focusReading(event) {
                const el = this.$refs[event.detail.ref];
                if (el) {
                    el.focus();
                    if (typeof el.select === 'function') {
                        el.select();
                    }
                }
            }
        }"
        x-on:focus-reading.window="focusReading($event)"
        class="rw-monthly-billing mx-auto max-w-full space-y-6 px-4 py-2 sm:px-6 lg:px-8"
    >
        {{-- Subscription Warnings --}}
        @if($access === \App\Enums\SubscriptionAccess::PastDue)
            <div class="rw-billing-alert rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100 flex items-start gap-3 shadow-sm">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                <div>
                    <p class="font-semibold">{{ __('Subscription past due') }}</p>
                    <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-300">{{ __('Please complete payment to restore full access.') }}</p>
                </div>
            </div>
        @elseif($access === \App\Enums\SubscriptionAccess::ReadOnly)
            <div class="rw-billing-alert rounded-lg border border-red-200 bg-red-50/50 p-4 text-sm text-red-900 dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-100 flex items-start gap-3 shadow-sm">
                <x-heroicon-o-lock-closed class="h-5 w-5 shrink-0 text-red-600 dark:text-red-400 mt-0.5" />
                <div>
                    <p class="font-semibold">{{ __('Write actions are disabled') }}</p>
                    <p class="mt-0.5 text-xs text-red-700 dark:text-red-300">{{ __('Your subscription is read-only until payment is completed.') }}</p>
                </div>
            </div>
        @endif

        {{-- Page Shell Header --}}
        <header class="rw-billing-header flex flex-col gap-2 pb-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight text-gray-955 dark:text-white">
                        {{ __('Utility billing') }}
                    </h1>
                    <span class="inline-flex items-center rounded-md bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">
                        {{ $this->sidebarPropertyLabel() }}
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
                <div class="flex items-center gap-1">
                    <x-heroicon-m-calendar class="h-3.5 w-3.5 text-gray-400" />
                    <span>{{ $this->issueDateLabel() }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <x-heroicon-m-home-modern class="h-3.5 w-3.5 text-gray-400" />
                    <span>
                        @if($selectedProperty)
                            @if($this->step === 'reading')
                                {{ $this->currentRoomProgress() }}
                            @else
                                {{ __(':count rooms due', ['count' => $this->dueRoomCount()]) }}
                            @endif
                        @else
                            {{ __('All properties') }}
                        @endif
                    </span>
                </div>
                <div class="flex items-center gap-1 text-gray-400 dark:text-gray-555">
                    <x-heroicon-m-information-circle class="h-3.5 w-3.5" />
                    <span>{{ __('To switch properties, use the selector in the sidebar.') }}</span>
                </div>
            </div>
        </header>
        {{-- Top Flow Progress Indicator --}}
        <nav class="rw-billing-steps flex items-center justify-between border-b border-gray-250 pb-4 dark:border-gray-800" aria-label="Progress">
            <ol role="list" class="flex w-full items-center justify-between gap-3 text-xs font-medium">
                @php
                    $flowSteps = [
                        'start' => ['label' => __('Start'), 'order' => 1],
                        'reading' => ['label' => __('Read'), 'order' => 2],
                        'review' => ['label' => __('Review'), 'order' => 3],
                        'result' => ['label' => __('Done'), 'order' => 4],
                    ];
                    $currentOrder = $flowSteps[$this->step]['order'] ?? 0;
                @endphp

                @foreach($flowSteps as $key => $stepData)
                    @php
                        $isCurrent = $this->step === $key;
                        $isCompleted = $currentOrder > $stepData['order'];
                    @endphp
                    <li class="flex-1">
                        <div class="flex flex-col border-t-2 pt-2 transition-colors {{ $isCurrent ? 'border-emerald-600 dark:border-emerald-500' : ($isCompleted ? 'border-gray-400 dark:border-gray-600' : 'border-gray-250 dark:border-gray-800') }}">
                            <span class="{{ $isCurrent ? 'text-emerald-600 dark:text-emerald-500 font-semibold' : ($isCompleted ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-555') }}">
                                {{ $stepData['label'] }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ol>
        </nav>

        {{-- Blocked state when no active property is selected --}}
        @if(! $selectedProperty)
            <div class="rw-active-property-empty mx-auto max-w-md rounded-xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900 my-8 space-y-4">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-home-modern class="h-6 w-6 text-gray-500 dark:text-gray-400" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ __('Select a property from the sidebar to start billing.') }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-normal">
                        {{ __('Billing runs one property at a time so rooms never mix.') }}
                    </p>
                </div>
                <div class="border-t border-gray-100 pt-3 dark:border-gray-800">
                    <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
                        {{ __('All properties') }}
                    </span>
                </div>
            </div>
        @elseif($this->step === 'start')
            <div class="rw-start-screen rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">
                        {{ __('Confirm billing details') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Set the billing date and verify active utilities before starting readings.') }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/30">
                        <label for="billing-date-input" class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-2">
                            {{ __('Billing date') }}
                        </label>
                        <input
                            id="billing-date-input"
                            type="date"
                            wire:model.live="issueDate"
                            class="w-full rounded-lg border-gray-300 bg-white text-base font-semibold text-gray-950 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white py-2 px-3"
                        >
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/30">
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-2">
                            {{ __('Rooms due') }}
                        </span>
                        <p class="text-3xl font-extrabold text-gray-950 dark:text-white mt-1">
                            {{ $this->dueRoomCount() }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/30">
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-2">
                            {{ __('Active utilities') }}
                        </span>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse($this->activeUtilities() as $utility)
                                <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">
                                    {{ __($utility->name) }}
                                </span>
                            @empty
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('No active utilities') }}
                                </span>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if(! $this->billingEnabled())
                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100 flex items-start gap-3">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                        <div>
                            <p class="font-semibold">{{ __('Monthly billing is disabled for this property.') }}</p>
                            <p class="mt-1 text-xs text-gray-655 dark:text-gray-400">{{ __('Please enable it in property settings before continuing.') }}</p>
                        </div>
                    </div>
                @elseif($this->activeUtilities()->isEmpty())
                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100 flex items-start gap-3">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                        <div>
                            <p class="font-semibold">{{ __('This property has no active utilities to bill.') }}</p>
                            <p class="mt-1 text-xs text-gray-655 dark:text-gray-400">{{ __('Add or activate utilities in property settings before continuing.') }}</p>
                        </div>
                    </div>
                @else
                    {{-- Mode switcher: Scheduled / Manual --}}
                    <div class="flex items-center gap-3 rounded-lg border border-gray-150 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-800/30">
                        <button
                            type="button"
                            wire:click="$set('manualMode', false)"
                            class="flex-1 py-1.5 px-3 text-xs font-semibold rounded-md border text-center transition {{ !$this->manualMode ? 'bg-primary-600 text-white border-primary-600 dark:bg-primary-500' : 'bg-white text-gray-700 dark:bg-gray-800 dark:text-gray-200 border-gray-200 dark:border-gray-700' }}"
                            id="btn-billing-mode-scheduled"
                        >
                            {{ __('Scheduled billing') }}
                        </button>
                        <button
                            type="button"
                            wire:click="$set('manualMode', true)"
                            class="flex-1 py-1.5 px-3 text-xs font-semibold rounded-md border text-center transition {{ $this->manualMode ? 'bg-primary-600 text-white border-primary-600 dark:bg-primary-500' : 'bg-white text-gray-700 dark:bg-gray-800 dark:text-gray-200 border-gray-200 dark:border-gray-700' }}"
                            id="btn-billing-mode-manual"
                        >
                            {{ __('Manual billing') }}
                        </button>
                    </div>

                    {{-- Checklist for manual selection --}}
                    @if($this->manualMode)
                        <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/30 space-y-3">
                            <div class="flex items-center justify-between border-b border-gray-200/50 pb-2 dark:border-gray-800">
                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ __('Select rooms to bill') }}
                                </span>
                                <button
                                    type="button"
                                    wire:click="toggleSelectAllRentals"
                                    class="text-xs text-primary-600 dark:text-primary-400 font-semibold hover:underline"
                                    id="btn-toggle-select-all-rentals"
                                >
                                    {{ __('Toggle all') }}
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 max-h-48 overflow-y-auto p-1">
                                @forelse($this->activeRentals() as $rental)
                                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                        <input
                                            type="checkbox"
                                            value="{{ $rental->id }}"
                                            wire:model.live="selectedRentalIds"
                                            class="rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500"
                                        >
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-gray-900 dark:text-white truncate">
                                                {{ $rental->unit?->room_number }}
                                            </p>
                                            <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                                {{ $rental->occupant_name }}
                                            </p>
                                        </div>
                                    </label>
                                @empty
                                    <div class="col-span-full py-4 text-center text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('No active tenancies in this property.') }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end pt-2">
                        @if($this->manualMode)
                            <x-filament::button
                                type="button"
                                icon="heroicon-o-play"
                                wire:click="startBilling"
                                :disabled="count($this->selectedRentalIds) === 0"
                                class="w-full sm:w-auto text-sm py-2.5 px-5 font-semibold"
                                id="btn-start-manual-billing"
                            >
                                {{ __('Start billing') }}
                            </x-filament::button>
                        @else
                            @if($this->dueRoomCount() > 0)
                                <x-filament::button
                                    type="button"
                                    icon="heroicon-o-play"
                                    wire:click="startBilling"
                                    class="w-full sm:w-auto text-sm py-2.5 px-5 font-semibold"
                                    id="btn-start-scheduled-billing"
                                >
                                    {{ __('Start billing') }}
                                </x-filament::button>
                            @else
                                <div class="w-full rounded-xl border border-gray-250 bg-gray-50 p-4 text-center dark:border-gray-800 dark:bg-gray-800/40">
                                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                        {{ __('No rooms are due for billing on this date.') }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('Try changing the billing date above, select another property, or select Manual billing.') }}
                                    </p>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

        {{-- Step 3: Desktop Reading Workspace --}}
        @elseif($this->step === 'reading')
            {{-- Desktop Grid View --}}
            <div class="space-y-6">
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ __('Room') }}</th>
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ __('Tenant') }}</th>
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white min-w-[240px]">{{ __('Billing period') }}</th>
                                    @foreach($this->activeUtilities() as $utility)
                                        <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white min-w-[160px]">{{ __($utility->name) }}</th>
                                    @endforeach
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white text-right">{{ __('Est. Total') }}</th>
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white text-center">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                @foreach($this->rooms as $index => $room)
                                    @php($summary = $this->roomSummary($index))
                                    <tr class="{{ $room['skipped'] ? 'opacity-50 bg-gray-50/50 dark:bg-gray-800/10' : '' }} hover:bg-gray-50/30 dark:hover:bg-gray-800/10 transition-colors">
                                        <!-- Room Number -->
                                        <td class="px-4 py-3 font-bold text-gray-950 dark:text-white">
                                            {{ $room['room_number'] }}
                                        </td>
                                        <!-- Occupant -->
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 dark:text-white truncate max-w-[150px]">
                                                {{ $room['occupant_name'] }}
                                            </div>
                                        </td>
                                        <!-- Period Controls -->
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-1.5">
                                                <input
                                                    type="date"
                                                    wire:model.live="rooms.{{ $index }}.period_start"
                                                    class="rounded-lg border-gray-300 bg-white text-xs px-2 py-1 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                    {{ $room['skipped'] ? 'disabled' : '' }}
                                                >
                                                <span class="text-gray-400 dark:text-gray-600">&rarr;</span>
                                                <input
                                                    type="date"
                                                    wire:model.live="rooms.{{ $index }}.period_end"
                                                    class="rounded-lg border-gray-300 bg-white text-xs px-2 py-1 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                    {{ $room['skipped'] ? 'disabled' : '' }}
                                                >
                                            </div>
                                            {{-- Warnings if any --}}
                                            @if($this->roomHasInvalidPeriodOrDuplicate($index))
                                                <div class="mt-1 text-[10px] text-red-650 dark:text-red-400 font-semibold space-y-0.5">
                                                    @if(\Carbon\Carbon::parse($room['period_start'])->isAfter(\Carbon\Carbon::parse($room['period_end'])))
                                                        <span>⚠️ {{ __('Period start is after end') }}</span>
                                                    @elseif($this->hasDuplicateInvoice($index))
                                                        <span>⚠️ {{ __('Duplicate invoice exists') }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <!-- Metred Utilities -->
                                        @foreach($room['utilities'] as $utilityIndex => $utility)
                                            @php($preview = $this->utilityPreview($index, $utilityIndex))
                                            <td class="px-4 py-3">
                                                @if($utility['requires_reading'] ?? true)
                                                    <div class="text-[10px] text-gray-500 dark:text-gray-400">
                                                        {{ __('Prev') }}: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $preview['old_reading'] !== null ? $this->formatQuantity($preview['old_reading']) : '—' }}</span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        step="any"
                                                        inputmode="decimal"
                                                        wire:model.live.debounce.300ms="rooms.{{ $index }}.utilities.{{ $utilityIndex }}.new_reading"
                                                        class="w-full rounded-lg border-gray-300 bg-white px-2 py-1 text-xs font-semibold shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                        placeholder="{{ __('New') }}"
                                                        {{ $room['skipped'] ? 'disabled' : '' }}
                                                    >
                                                    @if($preview['is_lower_reading'])
                                                        <div class="mt-1">
                                                            <input
                                                                type="text"
                                                                wire:model.live.debounce.300ms="rooms.{{ $index }}.utilities.{{ $utilityIndex }}.override_reason"
                                                                placeholder="{{ __('Reason for lower') }}"
                                                                class="w-full rounded-md border-amber-300 bg-amber-50/50 px-2 py-0.5 text-[10px] focus:border-amber-500 dark:border-amber-900/30 dark:bg-amber-950/20 text-gray-955 dark:text-white"
                                                                {{ $room['skipped'] ? 'disabled' : '' }}
                                                            >
                                                        </div>
                                                    @endif
                                                    @if($preview['amount_used'] !== null && !$room['skipped'])
                                                        <div class="text-[10px] text-emerald-600 dark:text-emerald-500 mt-1 font-semibold">
                                                            {{ $this->formatQuantity($preview['amount_used']) }} {{ $utility['unit_of_measure'] }} &middot; {{ $this->formatMoney($preview['charge']) }}
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ __('Fixed') }}: {{ $this->formatMoney($preview['charge']) }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <!-- Estimated Invoice Total -->
                                        <td class="px-4 py-3 text-right font-bold text-gray-950 dark:text-white">
                                            {{ $this->formatMoney($summary['estimated_total']) }}
                                        </td>
                                        <!-- Skip / Unskip -->
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                type="button"
                                                wire:click="toggleRoomSkip({{ $index }})"
                                                class="text-xs font-semibold transition {{ $room['skipped'] ? 'text-primary-600 dark:text-primary-400 hover:underline' : 'text-red-600 hover:text-red-700 hover:underline' }}"
                                            >
                                                {{ $room['skipped'] ? __('Unskip') : __('Skip') }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Desktop bottom controls --}}
                    <div class="flex items-center justify-between pt-4">
                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="$set('step', 'start')"
                            class="text-xs py-2 px-4"
                        >
                            {{ __('Back') }}
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            icon="heroicon-o-check-circle"
                            icon-position="after"
                            wire:click="goToReview"
                            class="text-xs py-2 px-4"
                        >
                            {{ __('Go to Review') }}
                        </x-filament::button>
                    </div>
            </div>

        {{-- Step 4: Review Screen --}}
        @elseif($this->step === 'review')
            <div
                class="space-y-4"
                @if($this->reviewFocusIndex !== null)
                    x-init="$nextTick(() => document.getElementById('review-room-{{ $this->reviewFocusIndex }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))"
                @endif
            >
                <!-- Summary Card -->
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                        <div>
                            <h2 class="text-lg font-bold text-gray-950 dark:text-white">
                                {{ __('Review billing summary') }}
                            </h2>
                            <p class="text-xs text-gray-550 dark:text-gray-400">
                                {{ __('Review all room charges and resolve issues before creating invoices.') }}
                            </p>
                        </div>
                        @if($access !== \App\Enums\SubscriptionAccess::ReadOnly)
                            <x-filament::button
                                type="button"
                                icon="heroicon-o-check-circle"
                                wire:click="openCreateConfirmation"
                                class="w-full sm:w-auto"
                            >
                                {{ __('Create invoices') }}
                            </x-filament::button>
                        @endif
                    </div>

                    <div class="rw-billing-stats">
                        <div class="rounded-lg bg-gray-50/50 p-3 dark:bg-gray-800/30">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block">{{ __('Completed rooms') }}</span>
                            <span class="text-2xl font-bold text-gray-950 dark:text-white mt-1 block">{{ $this->completeRoomCount() }}</span>
                        </div>
                        <div class="rounded-lg bg-gray-50/50 p-3 dark:bg-gray-800/30">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block">{{ __('Skipped rooms') }}</span>
                            <span class="text-2xl font-bold text-gray-950 dark:text-white mt-1 block">{{ $this->skippedRoomCount() }}</span>
                        </div>
                        <div class="rounded-lg bg-gray-50/50 p-3 dark:bg-gray-800/30">
                            <span class="text-xs font-medium text-gray-550 dark:text-gray-400 block">{{ __('Rooms with warnings') }}</span>
                            <span class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1 block">{{ $this->roomsWithWarningsCount() }}</span>
                        </div>
                        <div class="rounded-lg bg-gray-50/50 p-3 dark:bg-gray-800/30">
                            <span class="text-xs font-medium text-gray-550 dark:text-gray-400 block">{{ __('Estimated invoices') }}</span>
                            <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">{{ $this->estimatedInvoiceCount() }}</span>
                        </div>
                    </div>
                </div>

                <!-- Room Cards -->
                <div class="space-y-4">
                    @foreach($rooms as $index => $room)
                        @php($summary = $this->roomSummary($index))
                        <div
                            id="review-room-{{ $index }}"
                            class="rw-review-card rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-4 transition hover:border-gray-300 dark:hover:border-gray-700"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-xl font-bold text-gray-950 dark:text-white">
                                            {{ __('Room') }} {{ $room['room_number'] }}
                                        </h3>
                                        @if($summary['is_skipped'])
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                                {{ __('Skipped') }}
                                            </span>
                                        @elseif($summary['warning_count'] > 0)
                                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20">
                                                {{ __('Warning') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">
                                                {{ __('Ready') }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                        {{ $room['occupant_name'] }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-filament::button
                                        type="button"
                                        color="gray"
                                        wire:click="editRoom({{ $index }})"
                                        class="text-xs py-1 px-2.5"
                                    >
                                        {{ __('Edit readings') }}
                                    </x-filament::button>
                            </div>
                        </div>

                        <div class="rw-review-card-grid">
                                <div class="rounded-lg bg-gray-50/50 p-3 dark:bg-gray-800/30">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block">{{ __('Utilities') }}</span>
                                    <span class="text-sm font-semibold text-gray-950 dark:text-white mt-1 block truncate" title="{{ $summary['utility_summary'] }}">
                                        {{ $summary['utility_summary'] }}
                                    </span>
                                </div>
                                <div class="rounded-lg bg-gray-50/50 p-3 dark:bg-gray-800/30">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block">{{ __('Estimated total') }}</span>
                                    <span class="text-base font-bold text-emerald-600 dark:text-emerald-400 mt-1 block">
                                        {{ $this->formatMoney($summary['estimated_total']) }}
                                    </span>
                                </div>
                            </div>

                            @if($summary['warnings'])
                                <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 text-xs text-amber-900 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-100 space-y-1">
                                    <p class="font-bold">{{ __('Warnings') }}:</p>
                                    <ul class="list-disc pl-4 space-y-0.5">
                                        @foreach($summary['warnings'] as $warning)
                                            <li>{{ $warning }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="flex justify-start">
                                <x-filament::button
                                    type="button"
                                    color="{{ $summary['is_skipped'] ? 'success' : 'warning' }}"
                                    wire:click="toggleRoomSkip({{ $index }})"
                                    class="text-xs py-1 px-3"
                                >
                                    {{ $summary['is_skipped'] ? __('Unskip room') : __('Skip room') }}
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Sticky Bottom Bar -->
                <div class="rw-billing-action-bar sticky bottom-0 z-40 rounded-xl border border-gray-250 bg-white p-4 shadow-lg dark:border-gray-850 dark:bg-gray-950 backdrop-blur-md bg-opacity-95">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Review complete? Ready to batch-generate client invoices.') }}
                        </p>

                        @if($access !== \App\Enums\SubscriptionAccess::ReadOnly)
                            <x-filament::button
                                type="button"
                                icon="heroicon-o-check-circle"
                                wire:click="openCreateConfirmation"
                                class="w-full sm:w-auto text-sm py-2 px-4"
                            >
                                {{ __('Create invoices') }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </div>

        {{-- Step 5: Result Screen --}}
        @else
            <div class="rw-result-screen rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 max-w-xl mx-auto space-y-6">
                <!-- Restrained Success Banner -->
                <div class="text-center pb-4 border-b border-gray-100 dark:border-gray-800">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-500/10">
                        <x-heroicon-o-check class="h-6 w-6 text-emerald-600 dark:text-emerald-500" />
                    </div>
                    <h2 class="text-xl font-bold text-gray-955 dark:text-white mt-4">
                        {{ __('Billing complete') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ __('All processed rooms have been billed for this period.') }}
                    </p>
                </div>

                <!-- Operational Receipt Summary -->
                <div class="space-y-3 text-sm">
                    <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-550 uppercase tracking-wider">
                        {{ __('Billing run summary') }}
                    </h3>

                    <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/30 space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('Invoices created') }}:</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $this->resultSummary['created'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('Rooms skipped') }}:</span>
                            <span class="font-bold text-gray-700 dark:text-gray-300">{{ $this->resultSummary['skipped'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('Failed runs') }}:</span>
                            <span class="font-bold {{ $this->resultSummary['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">
                                {{ $this->resultSummary['failed'] }}
                            </span>
                        </div>
                    </div>
                </div>

                @if($this->resultSummary['failures'])
                    <div class="space-y-2">
                        <h4 class="text-xs font-semibold text-red-650 dark:text-red-400 uppercase tracking-wider">
                            {{ __('Details of failures') }}
                        </h4>
                        <div class="max-h-40 overflow-y-auto rounded-lg border border-red-100 bg-red-50/30 p-3 dark:border-red-900/30 dark:bg-red-950/20 text-xs text-red-900 dark:text-red-205 space-y-2">
                            @foreach($this->resultSummary['failures'] as $failure)
                                <div>
                                    <span class="font-bold">{{ __('Room') }} {{ $failure['room_number'] }}</span>: {{ $failure['message'] }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Actions -->
                <div class="flex flex-col gap-2.5 pt-2">
                    <x-filament::button
                        tag="a"
                        href="{{ $this->viewInvoicesUrl() }}"
                        icon="heroicon-o-document-text"
                        class="w-full text-xs py-2 px-4"
                    >
                        {{ __('View invoices') }}
                    </x-filament::button>

                    @if($this->visibleProperties()->count() > 1)
                        <span class="text-center text-xs text-gray-400 dark:text-gray-550 my-1">
                            {{ __('To bill another property, use the sidebar selector.') }}
                        </span>
                    @endif

                    <x-filament::button
                        tag="a"
                        color="gray"
                        href="{{ $this->dashboardUrl() }}"
                        class="w-full text-xs py-2 px-4"
                    >
                        {{ __('Back to dashboard') }}
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- Confirmation Modal --}}
        @if($this->showCreateConfirmation)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 p-4 backdrop-blur-sm transition-opacity">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-950 dark:text-white">
                            {{ __('Confirm invoice creation') }}
                        </h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('You are about to generate utility invoices for this property.') }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800/50 space-y-2.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('Invoices to create') }}:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $this->estimatedInvoiceCount() }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ __('Rooms to skip') }}:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $this->skippedRoomCount() }}</span>
                        </div>
                        @if($this->roomsWithWarningsCount() > 0)
                            <div class="flex justify-between text-amber-600 dark:text-amber-400">
                                <span>{{ __('Rooms with warnings') }}:</span>
                                <span class="font-bold">{{ $this->roomsWithWarningsCount() }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col gap-2.5 sm:flex-row sm:justify-end">
                        <x-filament::button
                            type="button"
                            color="gray"
                            wire:click="cancelCreateConfirmation"
                            class="w-full sm:w-auto"
                        >
                            {{ __('Cancel') }}
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            wire:click="createInvoices"
                            class="w-full sm:w-auto"
                        >
                            {{ __('Create invoices') }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif
    </div>
@if(! $this->embedded)
</x-filament-panels::page>
@endif
</div>
