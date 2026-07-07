<x-filament-panels::page>
    @php
        $propertyId  = $this->getPropertyId();
        $propertyName = $this->getPropertyName();
    @endphp

    {{-- ─────────────────────────────────────────────────────────── --}}
    {{-- Simple Mode Shell                                          --}}
    {{-- ─────────────────────────────────────────────────────────── --}}
    <div
        class="rw-simple mx-auto max-w-lg space-y-5 px-4 py-4"
        x-data="{
            screen: new URLSearchParams(window.location.search).get('screen') || 'home',
            setScreen(s) {
                this.screen = s;
                history.replaceState(null, '', window.location.pathname + '?screen=' + s);
            }
        }"
    >
        {{-- ── Header bar ── --}}
        <div class="rw-sm-header flex items-center justify-between gap-3">
            {{-- Property name --}}
            <div class="min-w-0 flex-1">
                @if($propertyName)
                    <p class="rw-sm-property-label truncate text-xs font-semibold uppercase tracking-widest text-primary-600 dark:text-primary-400">
                        {{ $propertyName }}
                    </p>
                @else
                    <p class="text-xs text-gray-400">{{ __('No property selected') }}</p>
                @endif
                <h1 class="text-xl font-bold text-gray-900 dark:text-white leading-tight">{{ __('Daily work') }}</h1>
            </div>

        </div>

        {{-- ── No property blocked state ── --}}
        @if(! $propertyId)
            <div class="rw-sm-empty-state rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center">
                <div class="mb-3 text-4xl">🏠</div>
                <p class="font-semibold text-gray-700 dark:text-gray-300">{{ __('Choose a property first') }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ __('Use the sidebar property switcher to select a property.') }}</p>
            </div>

        @else
            {{-- ── Home: action grid ── --}}
            <div x-show="screen === 'home'" x-cloak>
                <div class="rw-sm-grid grid grid-cols-2 gap-3">
                    {{-- Create Invoices --}}
                    <button
                        @click="setScreen('billing-invoice')"
                       class="rw-sm-action-card text-left"
                       id="simple-action-billing"
                    >
                        <div class="rw-sm-action-icon bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
                        </div>
                        <span class="rw-sm-action-label">{{ __('Create invoices') }}</span>
                        <span class="rw-sm-action-sub">{{ __('Monthly billing') }}</span>
                    </button>

                    {{-- Invoices --}}
                    <button
                        @click="setScreen('invoices')"
                        class="rw-sm-action-card text-left"
                        id="simple-action-invoices"
                    >
                        <div class="rw-sm-action-icon bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776"/></svg>
                        </div>
                        <span class="rw-sm-action-label">{{ __('Invoices') }}</span>
                        <span class="rw-sm-action-sub">{{ __('Check & filter') }}</span>
                    </button>

                    {{-- Record Payment --}}
                    <button
                        @click="setScreen('invoices')"
                        class="rw-sm-action-card text-left"
                        id="simple-action-payment"
                    >
                        <div class="rw-sm-action-icon bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/></svg>
                        </div>
                        <span class="rw-sm-action-label">{{ __('Record payment') }}</span>
                        <span class="rw-sm-action-sub">{{ __('Unpaid invoices') }}</span>
                    </button>

                    {{-- Add Tenant --}}
                    <button
                        @click="setScreen('add-tenant')"
                        class="rw-sm-action-card text-left"
                        id="simple-action-add-tenant"
                    >
                        <div class="rw-sm-action-icon bg-violet-100 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
                        </div>
                        <span class="rw-sm-action-label">{{ __('Add tenant') }}</span>
                        <span class="rw-sm-action-sub">{{ __('New tenancy') }}</span>
                    </button>

                    {{-- End Tenancy --}}
                    <button
                        @click="setScreen('end-tenancy')"
                        class="rw-sm-action-card text-left"
                        id="simple-action-end-tenancy"
                    >
                        <div class="rw-sm-action-icon bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                        </div>
                        <span class="rw-sm-action-label">{{ __('End tenancy') }}</span>
                        <span class="rw-sm-action-sub">{{ __('Move-out') }}</span>
                    </button>

                    {{-- Rooms --}}
                    <button
                        @click="setScreen('rooms')"
                        class="rw-sm-action-card text-left"
                        id="simple-action-rooms"
                    >
                        <div class="rw-sm-action-icon bg-sky-100 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>
                        </div>
                        <span class="rw-sm-action-label">{{ __('Rooms') }}</span>
                        <span class="rw-sm-action-sub">{{ __('Room status') }}</span>
                    </button>
                </div>
            </div>

            {{-- ── Invoices screen ── --}}
            <div x-show="screen === 'invoices'" x-cloak>
                <div class="rw-sm-screen-header">
                    <button @click="setScreen('home')" class="rw-sm-back-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                    </button>
                    <h2 class="rw-sm-screen-title">{{ __('Invoices') }}</h2>
                </div>
                @livewire('simple-invoice-list', key('simple-invoice-list'))
            </div>

            {{-- ── Billing invoice screen ── --}}
            <div x-show="screen === 'billing-invoice'" x-cloak>
                <div class="rw-sm-screen-header">
                    <button @click="setScreen('home')" class="rw-sm-back-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                    </button>
                    <h2 class="rw-sm-screen-title">{{ __('Create invoices') }}</h2>
                </div>
                <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    @livewire(\App\Livewire\SimpleBillingInvoice::class, key('simple-billing-invoice'))
                </div>
            </div>

            {{-- ── Add tenant screen ── --}}
            <div x-show="screen === 'add-tenant'" x-cloak>
                <div class="rw-sm-screen-header">
                    <button @click="setScreen('home')" class="rw-sm-back-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                    </button>
                    <h2 class="rw-sm-screen-title">{{ __('Add tenant') }}</h2>
                </div>
                @livewire('simple-add-tenant', key('simple-add-tenant'))
            </div>

            {{-- ── End tenancy screen ── --}}
            <div x-show="screen === 'end-tenancy'" x-cloak>
                <div class="rw-sm-screen-header">
                    <button @click="setScreen('home')" class="rw-sm-back-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                    </button>
                    <h2 class="rw-sm-screen-title">{{ __('End tenancy') }}</h2>
                </div>
                @livewire('simple-end-tenancy', key('simple-end-tenancy'))
            </div>

            {{-- ── Rooms screen ── --}}
            <div x-show="screen === 'rooms'" x-cloak>
                <div class="rw-sm-screen-header">
                    <button @click="setScreen('home')" class="rw-sm-back-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                    </button>
                    <h2 class="rw-sm-screen-title">{{ __('Rooms') }}</h2>
                </div>
                @livewire('simple-room-list', key('simple-room-list'))
            </div>
        @endif
    </div>
</x-filament-panels::page>
