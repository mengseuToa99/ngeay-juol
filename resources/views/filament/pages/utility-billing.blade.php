<div class="rw-utility-billing-root">
@if(! $this->embedded)
<x-filament-panels::page>
@endif
    @php
        $access = $this->getAccess();
        $selectedProperty = $this->selectedProperty();
        $filteredRooms = $this->filteredRooms();
        $activeUtilities = $this->activeUtilities();
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
        class="rw-utility-billing mx-auto max-w-full space-y-6 px-4 py-2 sm:px-6 lg:px-8"
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

        {{-- Result Screen when billing is complete --}}
        @elseif($this->step === 'result')
            <div class="rw-result-screen rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 max-w-xl mx-auto space-y-6">
                <!-- Success Header -->
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

                <!-- Run Summary -->
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

                <!-- Failures details -->
                @if($this->resultSummary['failures'])
                    <div class="space-y-2">
                        <h4 class="text-xs font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider">
                            {{ __('Details of failures') }}
                        </h4>
                        <div class="max-h-40 overflow-y-auto rounded-lg border border-red-100 bg-red-50/30 p-3 dark:border-red-900/30 dark:bg-red-950/20 text-xs text-red-900 dark:text-red-200 space-y-2">
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
                        class="w-full text-sm py-2 px-4"
                    >
                        {{ __('View invoices') }}
                    </x-filament::button>

                    @if($this->visibleProperties()->count() > 1)
                        <span class="text-center text-xs text-gray-400 dark:text-gray-500 my-1">
                            {{ __('To bill another property, use the sidebar selector.') }}
                        </span>
                    @endif

                    <x-filament::button
                        tag="a"
                        color="gray"
                        href="{{ $this->dashboardUrl() }}"
                        class="w-full text-sm py-2 px-4"
                    >
                        {{ __('Back to dashboard') }}
                    </x-filament::button>
                </div>
            </div>

        {{-- Desktop-First Reading Workspace (Main Screen) --}}
        @else
            {{-- Toolbar/Header --}}
            <div id="utility-billing-toolbar" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-4">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white flex items-center gap-2">
                            <span>{{ __('Utility billing workspace') }}</span>
                            <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/15 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">
                                {{ $this->selectedPropertyName() }}
                            </span>
                        </h1>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Fast entry desktop dashboard for property utilities billing') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        {{-- Scheduled/Manual mode controls --}}
                        <div class="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50/50 p-1 dark:border-gray-700 dark:bg-gray-800/30">
                            <button
                                type="button"
                                wire:click="$set('manualMode', false)"
                                class="py-1 px-3 text-xs font-semibold rounded-md border-0 transition {{ !$this->manualMode ? 'bg-primary-600 text-white dark:bg-primary-500' : 'bg-transparent text-gray-700 dark:text-gray-300' }}"
                                id="btn-billing-mode-scheduled"
                            >
                                {{ __('Scheduled') }}
                            </button>
                            <button
                                type="button"
                                wire:click="$set('manualMode', true)"
                                class="py-1 px-3 text-xs font-semibold rounded-md border-0 transition {{ $this->manualMode ? 'bg-primary-600 text-white dark:bg-primary-500' : 'bg-transparent text-gray-700 dark:text-gray-300' }}"
                                id="btn-billing-mode-manual"
                            >
                                {{ __('Manual') }}
                            </button>
                        </div>

                        {{-- Issue date input --}}
                        <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-2.5 py-1 dark:border-gray-700 dark:bg-gray-900">
                            <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">{{ __('Date') }}</span>
                            <input
                                type="date"
                                wire:model.live="issueDate"
                                class="border-0 bg-transparent p-0 text-xs font-bold text-gray-950 focus:ring-0 dark:text-white max-w-[110px]"
                            >
                        </div>
                    </div>
                </div>

                {{-- Badges row --}}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-gray-100 pt-3 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex items-center gap-1">
                        <x-heroicon-m-bolt class="h-4 w-4 text-emerald-600 dark:text-emerald-500" />
                        <span>
                            {{ __('Active utilities') }}:
                            <strong>{{ count($activeUtilities) }}</strong>
                            ({{ $activeUtilities->pluck('name')->map(fn($n) => __($n))->implode(', ') ?: __('None') }})
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <x-heroicon-m-home-modern class="h-4 w-4 text-gray-400" />
                        <span>
                            {{ __('Rooms due') }}: <strong>{{ $this->dueRoomCount() }}</strong>
                        </span>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-800/30">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block">{{ __('Charges to bill') }}</span>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse($filteredRooms as $fRoom)
                                @php($summary = $this->roomSummary($fRoom['original_index']))
                                @foreach($summary['charges_to_bill'] as $charge)
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                        {{ $charge }}
                                    </span>
                                @endforeach
                            @empty
                                <span class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-800/30">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block">{{ __('Free or waived') }}</span>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse($filteredRooms as $fRoom)
                                @php($summary = $this->roomSummary($fRoom['original_index']))
                                @foreach($summary['free_or_waived'] as $charge)
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                        {{ $charge }}
                                    </span>
                                @endforeach
                            @empty
                                <span class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-800/30">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 block">{{ __('Not billed') }}</span>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse($filteredRooms as $fRoom)
                                @php($summary = $this->roomSummary($fRoom['original_index']))
                                @foreach($summary['not_billed'] as $charge)
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        {{ $charge }}
                                    </span>
                                @endforeach
                            @empty
                                <span class="text-[10px] text-gray-500 dark:text-gray-400">{{ __('None') }}</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Main workspace: Sidebar selector + Editable grid + Summary --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- Left manual list selector (only when manualMode is active) --}}
                @if($this->manualMode)
                    <div class="lg:col-span-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-3">
                        <div class="flex items-center justify-between border-b border-gray-200 pb-2 dark:border-gray-800">
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                {{ __('Rooms to bill') }} ({{ count($this->selectedRentalIds) }})
                            </span>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="selectAllRentals"
                                    class="text-xs text-primary-600 dark:text-primary-400 font-semibold hover:underline"
                                >
                                    {{ __('All') }}
                                </button>
                                <span class="text-gray-300 dark:text-gray-700">|</span>
                                <button
                                    type="button"
                                    wire:click="clearAllRentals"
                                    class="text-xs text-red-600 font-semibold hover:underline"
                                >
                                    {{ __('Clear') }}
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1.5 max-h-[500px] overflow-y-auto pr-1">
                            @forelse($this->activeRentals() as $rental)
                                @php
                                    $isSelected = in_array($rental->id, $this->selectedRentalIds);
                                    $cardClass = $isSelected
                                        ? 'border-primary-500 bg-primary-50/20 dark:border-primary-400/40 dark:bg-primary-500/5'
                                        : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/40';
                                @endphp
                                <label class="flex items-center gap-2.5 rounded-lg border p-2 cursor-pointer transition {{ $cardClass }}">
                                    <input
                                        type="checkbox"
                                        value="{{ $rental->id }}"
                                        wire:model.live="selectedRentalIds"
                                        class="rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500"
                                    >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex justify-between items-baseline">
                                            <p class="text-xs font-bold text-gray-900 dark:text-white truncate">
                                                {{ $rental->unit?->room_number }}
                                            </p>
                                        </div>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                            {{ $rental->occupant_name }}
                                        </p>
                                    </div>
                                </label>
                            @empty
                                <div class="py-6 text-center text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('No active tenancies.') }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif

                {{-- Central Editable Grid --}}
                <div class="{{ $this->manualMode ? 'lg:col-span-6' : 'lg:col-span-9' }} space-y-4">
                    {{-- Grid filter bar & search --}}
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        {{-- Filters tab --}}
                        <div class="flex flex-wrap items-center gap-1.5 border-b border-gray-200/50 pb-1 dark:border-gray-800">
                            @foreach([
                                'all' => __('All'),
                                'needs_input' => __('Needs input'),
                                'warnings' => __('Warnings only'),
                                'skipped' => __('Skipped'),
                                'ready' => __('Ready')
                            ] as $key => $label)
                                <button
                                    type="button"
                                    wire:click="$set('rowFilter', '{{ $key }}')"
                                    class="py-1 px-2.5 text-xs font-semibold rounded-lg transition {{ $this->rowFilter === $key ? 'bg-primary-600 text-white dark:bg-primary-500' : 'bg-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Search and Focus issue --}}
                        <div class="flex items-center gap-3">
                            <input
                                type="search"
                                wire:model.live.debounce.300ms="search"
                                placeholder="{{ __('Room or tenant name…') }}"
                                class="rounded-lg border-gray-300 bg-white text-xs px-2.5 py-1 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white placeholder-gray-400 w-full sm:w-[180px]"
                            >

                            <x-filament::button
                                type="button"
                                color="warning"
                                wire:click="focusFirstIssue"
                                class="text-xs py-1 px-2.5 shrink-0"
                            >
                                {{ __('Focus first issue') }}
                            </x-filament::button>
                        </div>
                    </div>

                    {{-- Large Spreadsheet Table --}}
                    <div id="utility-billing-grid" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white sticky left-0 bg-gray-50 dark:bg-gray-800 z-10">{{ __('Room') }}</th>
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white sticky left-[70px] bg-gray-50 dark:bg-gray-800 z-10">{{ __('Tenant') }}</th>
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white min-w-[240px]">{{ __('Billing period') }}</th>
                                    @foreach($activeUtilities as $utility)
                                        <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white min-w-[170px]">
                                            {{ __($utility->name) }}
                                            <span class="text-[10px] text-gray-500 font-normal">({{ $utility->currency ?: 'USD' }})</span>
                                        </th>
                                    @endforeach
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white text-right">{{ __('Est. Total') }}</th>
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white text-center">{{ __('Status') }}</th>
                                    <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white text-center">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                @forelse($filteredRooms as $fRoom)
                                    @php
                                        $originalIndex = $fRoom['original_index'];
                                        $room = $this->rooms[$originalIndex];
                                        $summary = $this->roomSummary($originalIndex);
                                        $warnings = $this->roomWarnings($originalIndex);
                                    @endphp
                                    <tr
                                        data-testid="utility-row-{{ $originalIndex }}"
                                        class="group {{ $room['skipped'] ? 'bg-gray-50/70 dark:bg-gray-950/40 text-gray-500 dark:text-gray-400' : 'text-gray-950 dark:text-gray-300' }} hover:bg-gray-50/30 dark:hover:bg-gray-800/10 transition-colors"
                                    >
                                        <!-- Room Number (Sticky) -->
                                        <td class="px-4 py-3 font-bold sticky left-0 {{ $room['skipped'] ? 'bg-gray-100 dark:bg-gray-950 text-gray-500 dark:text-gray-400' : 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white' }} group-hover:bg-gray-50 dark:group-hover:bg-gray-800 z-10 transition-colors shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">
                                            {{ $room['room_number'] }}
                                        </td>
                                        <!-- Occupant (Sticky) -->
                                        <td class="px-4 py-3 sticky left-[70px] {{ $room['skipped'] ? 'bg-gray-100 dark:bg-gray-950 text-gray-500 dark:text-gray-400' : 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white' }} group-hover:bg-gray-50 dark:group-hover:bg-gray-800 z-10 transition-colors shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">
                                            <div class="font-medium truncate max-w-[100px]">
                                                {{ $room['occupant_name'] }}
                                            </div>
                                        </td>
                                        <!-- Period Controls -->
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-1">
                                                <input
                                                    type="date"
                                                    wire:model.live="rooms.{{ $originalIndex }}.period_start"
                                                    class="rounded-lg border-gray-300 bg-white text-xs px-2 py-0.5 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white max-w-[115px] disabled:bg-gray-100 disabled:text-gray-400 dark:disabled:bg-gray-800 dark:disabled:text-gray-500"
                                                    {{ $room['skipped'] ? 'disabled' : '' }}
                                                >
                                                <span class="text-gray-400 dark:text-gray-600">&rarr;</span>
                                                <input
                                                    type="date"
                                                    wire:model.live="rooms.{{ $originalIndex }}.period_end"
                                                    class="rounded-lg border-gray-300 bg-white text-xs px-2 py-0.5 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white max-w-[115px] disabled:bg-gray-100 disabled:text-gray-400 dark:disabled:bg-gray-800 dark:disabled:text-gray-500"
                                                    {{ $room['skipped'] ? 'disabled' : '' }}
                                                >
                                            </div>
                                        </td>
                                        <!-- Metred Utilities -->
                                        @foreach($room['utilities'] as $utilityIndex => $utility)
                                            @php($preview = $this->utilityPreview($originalIndex, $utilityIndex))
                                            @php($state = $utility['state_override'] ?? 'normal')
                                            <td class="px-4 py-3">
                                                <div class="mb-1 flex flex-wrap items-center gap-1">
                                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-[9px] font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                        {{ \App\Services\ChargeRuleResolver::stateLabel((string) $state) }}
                                                    </span>
                                                    <span class="inline-flex items-center rounded-md bg-gray-50 px-1.5 py-0.5 text-[9px] font-semibold text-gray-500 dark:bg-gray-800/70 dark:text-gray-400">
                                                        {{ $utility['source_scope_label'] ?? __('Inherited from property') }}
                                                    </span>
                                                </div>
                                                <select
                                                    wire:model.live="rooms.{{ $originalIndex }}.utilities.{{ $utilityIndex }}.state_override"
                                                    class="mb-1 w-full rounded-lg border-gray-300 bg-white px-2 py-1 text-[10px] shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                >
                                                    <option value="normal">{{ __('Normal') }}</option>
                                                    <option value="free">{{ __('Free') }}</option>
                                                    <option value="waived">{{ __('Waived') }}</option>
                                                    <option value="not_applicable">{{ __('Not applicable') }}</option>
                                                    <option value="skipped_this_cycle">{{ __('Skip this cycle') }}</option>
                                                    <option value="custom">{{ __('Custom amount') }}</option>
                                                </select>
                                                @if($state === 'custom')
                                                    <div class="mb-1 grid grid-cols-2 gap-1">
                                                        <input
                                                            type="number"
                                                            step="any"
                                                            wire:model.live="rooms.{{ $originalIndex }}.utilities.{{ $utilityIndex }}.override_amount"
                                                            class="rounded-lg border-gray-300 bg-white px-2 py-1 text-[10px] shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                            placeholder="{{ __('Amount') }}"
                                                        >
                                                        <select
                                                            wire:model.live="rooms.{{ $originalIndex }}.utilities.{{ $utilityIndex }}.override_currency"
                                                            class="rounded-lg border-gray-300 bg-white px-2 py-1 text-[10px] shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                                        >
                                                            <option value="USD">USD</option>
                                                            <option value="KHR">KHR</option>
                                                        </select>
                                                    </div>
                                                @endif
                                                @if(in_array($state, ['not_applicable', 'skipped_this_cycle'], true))
                                                    <div class="mb-1 text-[9px] text-gray-500 dark:text-gray-400">
                                                        {{ __('No reading required') }}
                                                    </div>
                                                @endif
                                                @if($utility['requires_reading'] ?? true)
                                                    <div class="text-[10px] text-gray-500 dark:text-gray-400 flex justify-between mb-0.5">
                                                        <span>{{ __('Previous') }}:</span>
                                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $preview['old_reading'] !== null ? $this->formatQuantity($preview['old_reading']) : '0' }}</span>
                                                    </div>
                                                    <input
                                                        type="number"
                                                        step="any"
                                                        inputmode="decimal"
                                                        wire:model.live.debounce.300ms="rooms.{{ $originalIndex }}.utilities.{{ $utilityIndex }}.new_reading"
                                                        x-ref="reading-{{ $originalIndex }}-{{ $utilityIndex }}"
                                                        data-testid="utility-reading-{{ $originalIndex }}-{{ $utilityIndex }}"
                                                        class="w-full rounded-lg border-gray-300 bg-white px-2 py-0.5 text-xs font-semibold shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white disabled:bg-gray-100 disabled:text-gray-400 dark:disabled:bg-gray-800 dark:disabled:text-gray-500"
                                                        placeholder="{{ __('New reading') }}"
                                                        {{ $room['skipped'] || in_array($state, ['not_applicable', 'skipped_this_cycle'], true) ? 'disabled' : '' }}
                                                    >
                                                    @if($preview['is_lower_reading'])
                                                        <div class="mt-1">
                                                            <input
                                                                type="text"
                                                                wire:model.live.debounce.300ms="rooms.{{ $originalIndex }}.utilities.{{ $utilityIndex }}.override_reason"
                                                                placeholder="{{ __('Reason required') }}"
                                                                class="w-full rounded-md border-amber-300 bg-amber-50/50 px-2 py-0.5 text-[10px] focus:border-amber-500 dark:border-amber-900/30 dark:bg-amber-950/20 text-gray-950 dark:text-white disabled:bg-amber-100/50 disabled:text-amber-600 dark:disabled:bg-amber-950/10 dark:disabled:text-amber-500"
                                                                {{ $room['skipped'] || in_array($state, ['not_applicable', 'skipped_this_cycle'], true) ? 'disabled' : '' }}
                                                            >
                                                        </div>
                                                    @endif
                                                    @if($preview['amount_used'] !== null && !$room['skipped'] && !in_array($state, ['not_applicable', 'skipped_this_cycle'], true))
                                                        <div class="text-[10px] mt-0.5 flex justify-between font-semibold">
                                                            <span class="text-gray-500">{{ __('Usage') }}: <strong class="text-gray-800 dark:text-gray-200">{{ $this->formatQuantity($preview['amount_used']) }} {{ $utility['unit_of_measure'] }}</strong></span>
                                                            <span class="text-emerald-600 dark:text-emerald-500">{{ \App\Support\Money::format($preview['charge'], $utility['currency'] ?? 'USD') }}</span>
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ __('Fixed') }}: {{ \App\Support\Money::format($preview['charge'], $utility['currency'] ?? 'USD') }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <!-- Est. Total -->
                                        <td class="px-4 py-3 text-right font-bold text-gray-950 dark:text-white">
                                            {{ $summary['estimated_total_display'] }}
                                        </td>
                                        <!-- Row Status & Warning labels -->
                                        <td class="px-4 py-3 text-center">
                                            <div class="flex flex-col items-center gap-1.5">
                                                @if($room['skipped'])
                                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                                        {{ __('Skipped') }}
                                                    </span>
                                                @elseif($this->roomHasInvalidPeriodOrDuplicate($originalIndex))
                                                    <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20">
                                                        {{ __('Warnings') }}
                                                    </span>
                                                @elseif($this->roomHasMissingReadings($originalIndex))
                                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 ring-1 ring-inset ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20">
                                                        {{ __('Needs input') }}
                                                    </span>
                                                @elseif($this->roomHasBlockingLowReadings($originalIndex))
                                                    <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700 ring-1 ring-inset ring-red-600/10 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20">
                                                        {{ __('Needs input') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20">
                                                        {{ __('Ready') }}
                                                    </span>
                                                @endif

                                                @if(!$room['skipped'] && $warnings)
                                                    <div class="text-[9px] text-amber-600 dark:text-amber-400 font-semibold space-y-0.5 max-w-[120px] text-center">
                                                        @foreach($warnings as $w)
                                                            <div class="truncate" title="{{ $w }}">&middot; {{ $w }}</div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <!-- Skip Toggle Action -->
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                type="button"
                                                wire:click="toggleRoomSkip({{ $originalIndex }})"
                                                class="text-xs font-semibold transition {{ $room['skipped'] ? 'text-primary-600 dark:text-primary-400 hover:underline' : 'text-red-600 hover:text-red-700 hover:underline' }}"
                                            >
                                                {{ $room['skipped'] ? __('Unskip') : __('Skip') }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($activeUtilities) + 6 }}" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('No rooms match the current filter') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Right Summary Panel --}}
                <div class="lg:col-span-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-6">
                    <div>
                        <h2 class="text-sm font-bold text-gray-950 dark:text-white uppercase tracking-wider">
                            {{ __('Workspace summary') }}
                        </h2>
                    </div>

                    <div class="space-y-3.5 text-xs">
                        <div class="flex justify-between items-center rounded-lg bg-gray-50/50 p-2.5 dark:bg-gray-800/30">
                            <span class="font-medium text-gray-500 dark:text-gray-400">{{ __('Ready rooms') }}:</span>
                            <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ $this->completeRoomCount() }} / {{ count($this->rooms) }}</span>
                        </div>
                        <div class="flex justify-between items-center rounded-lg bg-gray-50/50 p-2.5 dark:bg-gray-800/30">
                            <span class="font-medium text-gray-500 dark:text-gray-400">{{ __('Skipped rooms') }}:</span>
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ $this->skippedRoomCount() }}</span>
                        </div>
                        <div class="flex justify-between items-center rounded-lg bg-gray-50/50 p-2.5 dark:bg-gray-800/30">
                            <span class="font-medium text-gray-500 dark:text-gray-400">{{ __('Rooms with warnings') }}:</span>
                            <span class="text-sm font-bold text-amber-600 dark:text-amber-400">{{ $this->roomsWithWarningsCount() }}</span>
                        </div>
                        <div class="flex flex-col gap-1.5 rounded-lg bg-emerald-50/30 border border-emerald-200/20 p-3 dark:bg-emerald-950/5">
                            <span class="font-medium text-emerald-700 dark:text-emerald-400 uppercase tracking-wider text-[10px]">{{ __('Estimated utility total') }}</span>
                            <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">
                                {{ $this->formatMoney($this->estimatedUtilityTotal()) }}
                            </span>
                        </div>
                    </div>

                    {{-- Primary action button --}}
                    <div class="pt-2">
                        @if($access !== \App\Enums\SubscriptionAccess::ReadOnly)
                            <x-filament::button
                                type="button"
                                icon="heroicon-o-check-circle"
                                wire:click="openCreateConfirmation"
                                :disabled="count($this->rooms) === 0 || $this->completeRoomCount() === 0"
                                class="w-full text-sm py-2.5"
                                id="utility-create-invoices"
                            >
                                {{ __('Create utility invoices') }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>

            </div>
        @endif

        {{-- Confirmation Modal --}}
        @if($this->showCreateConfirmation)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/40 backdrop-blur-sm">
                <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-800 dark:bg-gray-900 space-y-4">
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
