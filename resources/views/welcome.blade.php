<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('ngeay juol') }} - {{ __('Property Management, Beautifully Simplified.') }}</title>
        <link rel="icon" href="{{ asset('Khmer%20House%20Key.svg') }}" type="image/svg+xml">
        
        <!-- Theme Detection Script -->
        <script>
            const theme = localStorage.getItem('theme') || localStorage.getItem('color-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
            }
            .font-display {
                font-family: 'Outfit', sans-serif;
            }
            .gradient-text {
                background: linear-gradient(135deg, #10b981 0%, #06b6d4 50%, #3b82f6 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            .glow-bg {
                filter: blur(150px);
                opacity: 0.15;
            }
            .glass {
                background: rgba(255, 255, 255, 0.45);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.25);
            }
            .dark .glass {
                background: rgba(15, 23, 42, 0.45);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.05);
            }
        </style>
    </head>
    <body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen relative overflow-x-hidden transition-colors duration-300">
        
        <!-- Decorative Ambient Lights -->
        <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] rounded-full bg-emerald-400 glow-bg pointer-events-none"></div>
        <div class="absolute top-[20%] right-[-10%] w-[500px] h-[500px] rounded-full bg-cyan-400 glow-bg pointer-events-none"></div>
        <div class="absolute bottom-[-10%] left-[20%] w-[700px] h-[700px] rounded-full bg-blue-400 glow-bg pointer-events-none"></div>

        <!-- Header -->
        <header class="fixed top-4 left-1/2 -translate-x-1/2 w-[92%] max-w-7xl z-50 rounded-2xl glass shadow-xl transition-all duration-300">
            <div class="px-6 py-4 flex items-center justify-between">
                <!-- Logo -->
                <a href="#" class="flex items-center gap-2 group">
                    <img src="{{ asset('Khmer%20House%20Key.svg') }}" alt="{{ __('ngeay juol') }}" class="h-10 w-10 rounded-xl shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition-transform duration-300">
                    <span class="font-display font-extrabold text-2xl tracking-tight text-slate-900 dark:text-white">{{ __('ngeay juol') }}</span>
                </a>

                <!-- Navigation Links (Desktop) -->
                <nav class="hidden md:flex items-center gap-8">
                    <a href="#features" class="font-medium text-slate-600 dark:text-slate-300 hover:text-emerald-500 dark:hover:text-emerald-400 transition-colors">{{ __('Features') }}</a>
                    <a href="#testimonials" class="font-medium text-slate-600 dark:text-slate-300 hover:text-emerald-500 dark:hover:text-emerald-400 transition-colors">{{ __('Testimonials') }}</a>
                    <a href="#pricing" class="font-medium text-slate-600 dark:text-slate-300 hover:text-emerald-500 dark:hover:text-emerald-400 transition-colors">{{ __('Pricing') }}</a>
                </nav>

                <!-- Action Buttons -->
                <div class="flex items-center gap-4">
                    <!-- Light/Dark Toggle -->
                    <button id="theme-toggle" class="p-2.5 rounded-xl bg-white/80 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all cursor-pointer shadow-sm">
                        <!-- Sun Icon -->
                        <svg id="sun-icon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                        <!-- Moon Icon -->
                        <svg id="moon-icon" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </button>

                    <!-- Language Selector Dropdown -->
                    <div class="relative" id="lang-dropdown-container">
                        <button id="lang-dropdown-btn" class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white/80 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all cursor-pointer shadow-sm text-sm font-semibold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21a9.07 9.07 0 01-3.058-2.517 8.997 8.997 0 01-2.222-3.838A8.901 8.901 0 014.5 12c0-1.74.5-3.364 1.358-4.743a8.997 8.997 0 012.222-2.838A9.07 9.07 0 0110.5 3m0 18a9.07 9.07 0 003.058-2.517 8.997 8.997 0 002.222-3.838A8.9 8.9 0 0019.5 12c0-1.74-.5-3.364-1.358-4.743a9.006 9.006 0 00-2.222-2.838A9.07 9.07 0 0013.5 3M10.5 21V3m0 18c-3.13 0-5.659-4.03-5.659-9a13.3 13.3 0 01.127-1.74A13.066 13.066 0 015.63 7m4.87 14c3.13 0 5.659-4.03 5.659-9a13.3 13.3 0 00-.127-1.74A13.066 13.066 0 0015.37 7m-4.87-4c-3.13 0-5.659 4.03-5.659 9m5.659-9c3.13 0 5.659 4.03 5.659 9m-11.318 0h11.318m0 0h11.318"></path></svg>
                            <span>{{ app()->getLocale() === 'km' ? 'ខ្មែរ' : 'English' }}</span>
                            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div id="lang-dropdown-menu" class="hidden absolute right-0 mt-2 w-32 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl py-1.5 z-50">
                            <a href="{{ route('locale.switch', 'en') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors {{ app()->getLocale() === 'en' ? 'font-bold text-emerald-500' : '' }}">
                                English
                            </a>
                            <a href="{{ route('locale.switch', 'km') }}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors {{ app()->getLocale() === 'km' ? 'font-bold text-emerald-500' : '' }}">
                                ខ្មែរ
                            </a>
                        </div>
                    </div>

                    @auth
                        @php
                            $user = Auth::user();
                            if ($user->isPlatformStaff()) {
                                $dashboardUrl = route('filament.admin.pages.dashboard');
                            } elseif ($user->hasAnyRole(['landlord', 'landlord_manager'])) {
                                $dashboardUrl = route('filament.landlord.pages.dashboard');
                            } else {
                                $dashboardUrl = route('portal.dashboard');
                            }
                        @endphp
                        <a href="{{ $dashboardUrl }}" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-semibold shadow-md shadow-emerald-500/20 hover:shadow-lg hover:shadow-emerald-500/30 hover:scale-[1.02] active:scale-[0.98] transition-all text-sm">
                            {{ __('Go to Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="hidden sm:inline-block font-semibold text-slate-700 dark:text-slate-200 hover:text-emerald-500 dark:hover:text-emerald-400 transition-colors mr-2 text-sm">
                            {{ __('Log In') }}
                        </a>
                        <a href="http://127.0.0.1:8000/link" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-semibold shadow-md shadow-emerald-500/20 hover:shadow-lg hover:shadow-emerald-500/30 hover:scale-[1.02] active:scale-[0.98] transition-all text-sm">
                            {{ __('Start Free') }}
                        </a>
                    @endauth

                    <!-- Mobile Menu Button -->
                    <button id="mobile-menu-btn" class="md:hidden p-2 rounded-xl text-slate-700 dark:text-slate-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Menu Dropdown -->
            <div id="mobile-menu" class="hidden border-t border-slate-100 dark:border-slate-800/50 px-6 py-4 flex flex-col gap-4 bg-white/95 dark:bg-slate-900/95 backdrop-blur-lg rounded-b-2xl">
                <a href="#features" class="font-medium py-1.5 text-slate-600 dark:text-slate-300 hover:text-emerald-500 transition-colors">{{ __('Features') }}</a>
                <a href="#testimonials" class="font-medium py-1.5 text-slate-600 dark:text-slate-300 hover:text-emerald-500 transition-colors">{{ __('Testimonials') }}</a>
                <a href="#pricing" class="font-medium py-1.5 text-slate-600 dark:text-slate-300 hover:text-emerald-500 transition-colors">{{ __('Pricing') }}</a>
                <div class="flex items-center gap-4 py-1.5">
                    <span class="text-sm font-medium text-slate-500">{{ __('Language') }}:</span>
                    <a href="{{ route('locale.switch', 'en') }}" class="text-sm {{ app()->getLocale() === 'en' ? 'font-bold text-emerald-500' : 'text-slate-600 dark:text-slate-400' }}">EN</a>
                    <span class="text-slate-300">|</span>
                    <a href="{{ route('locale.switch', 'km') }}" class="text-sm {{ app()->getLocale() === 'km' ? 'font-bold text-emerald-500' : 'text-slate-600 dark:text-slate-400' }}">ខ្មែរ</a>
                </div>
                @guest
                    <hr class="border-slate-100 dark:border-slate-800">
                    <a href="{{ route('login') }}" class="font-medium py-1.5 text-slate-600 dark:text-slate-300 hover:text-emerald-500 transition-colors">{{ __('Log In') }}</a>
                @endguest
            </div>
        </header>

        <!-- Hero Section -->
        <section class="pt-32 pb-24 px-6 md:pt-48 md:pb-36 flex flex-col items-center text-center max-w-7xl mx-auto relative z-10">
            <!-- Badge -->
            <div class="mb-6 px-4 py-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-semibold text-xs tracking-wider uppercase flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                {{ __('Reimagining Property Management') }}
            </div>

            <!-- Title -->
            <h1 class="font-display font-extrabold text-4xl sm:text-6xl md:text-7.5xl tracking-tight text-slate-900 dark:text-white max-w-5xl leading-[1.1] mb-6">
                {{ __('Property Management, Beautifully Simplified.') }}
            </h1>

            <!-- Subtitle -->
            <p class="text-lg md:text-xl text-slate-600 dark:text-slate-400 max-w-3xl leading-relaxed mb-10">
                {{ __('The all-in-one platform for modern landlords and tenants. Automate rent invoices, track water/electricity utilities, and monitor revenues effortlessly.') }}
            </p>

            <!-- Hero CTAs -->
            <div class="flex flex-col sm:flex-row gap-4 mb-20">
                <a href="http://127.0.0.1:8000/link" class="px-8 py-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold shadow-lg shadow-emerald-500/20 hover:shadow-xl hover:shadow-emerald-500/30 hover:scale-[1.03] active:scale-[0.97] transition-all">
                    {{ __('Start Your Free Trial') }}
                </a>
                <a href="#features" class="px-8 py-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-all flex items-center justify-center gap-2">
                    {{ __('Explore Features') }}
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                </a>
            </div>

            <!-- Quick Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 w-full max-w-5xl">
                <!-- Stat Card 1 -->
                <div class="p-6 rounded-2xl bg-white/50 dark:bg-slate-900/50 border border-slate-200/50 dark:border-slate-800/50 flex flex-col items-center">
                    <span class="font-display font-extrabold text-4xl text-emerald-500 dark:text-emerald-400 mb-2">2,400+</span>
                    <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Properties Managed') }}</span>
                </div>
                <!-- Stat Card 2 -->
                <div class="p-6 rounded-2xl bg-white/50 dark:bg-slate-900/50 border border-slate-200/50 dark:border-slate-800/50 flex flex-col items-center">
                    <span class="font-display font-extrabold text-4xl text-cyan-500 dark:text-cyan-400 mb-2">$1.2M+</span>
                    <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Monthly Rent Collected') }}</span>
                </div>
                <!-- Stat Card 3 -->
                <div class="p-6 rounded-2xl bg-white/50 dark:bg-slate-900/50 border border-slate-200/50 dark:border-slate-800/50 flex flex-col items-center">
                    <span class="font-display font-extrabold text-4xl text-blue-500 dark:text-blue-400 mb-2">99.8%</span>
                    <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('On-Time Payments') }}</span>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-24 px-6 max-w-7xl mx-auto relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="font-display font-bold text-3xl md:text-5xl mb-4">{{ __('Everything You Need to Scale') }}</h2>
                <p class="text-lg text-slate-600 dark:text-slate-400">{{ __('Ditch the spreadsheets. Take full control of your portfolio with modern tools engineered for speed and simplicity.') }}</p>
            </div>

            <!-- Features Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Card 1 -->
                <div class="group p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/65 dark:border-slate-800/50 hover:border-emerald-500/50 hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all"></div>
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="font-display font-bold text-xl mb-3">{{ __('Smart Invoicing') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-sm">
                        {{ __('Generate professional A4, A5, and thermal receipts automatically. Export invoices to PDF or Excel in one click, fully localized in English and Khmer.') }}
                    </p>
                </div>

                <!-- Card 2 -->
                <div class="group p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/65 dark:border-slate-800/50 hover:border-cyan-500/50 hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-cyan-500/10 rounded-full blur-2xl group-hover:bg-cyan-500/20 transition-all"></div>
                    <div class="w-12 h-12 rounded-xl bg-cyan-500/10 text-cyan-500 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <h3 class="font-display font-bold text-xl mb-3">{{ __('Unified Tenant Portal') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-sm">
                        {{ __('Tenants log in to their read-only portal using credentials generated by their landlord. No complex signups—just instant access to invoices and payment details.') }}
                    </p>
                </div>

                <!-- Card 3 -->
                <div class="group p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/65 dark:border-slate-800/50 hover:border-blue-500/50 hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-all"></div>
                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="font-display font-bold text-xl mb-3">{{ __('Utility Tracker') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-sm">
                        {{ __('Log meter readings for water and electricity with zero friction. Auto-calculate utility usage differentials and add them straight to the monthly tenant bills.') }}
                    </p>
                </div>

                <!-- Card 4 -->
                <div class="group p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/65 dark:border-slate-800/50 hover:border-purple-500/50 hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition-all"></div>
                    <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                    </div>
                    <h3 class="font-display font-bold text-xl mb-3">{{ __('Portfolio Dashboards') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-sm">
                        {{ __('Keep your finger on the pulse with interactive widgets. Track occupancy rates, view recurring revenues, and keep track of overdue rent logs.') }}
                    </p>
                </div>

                <!-- Card 5 -->
                <div class="group p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/65 dark:border-slate-800/50 hover:border-pink-500/50 hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-pink-500/10 rounded-full blur-2xl group-hover:bg-pink-500/20 transition-all"></div>
                    <div class="w-12 h-12 rounded-xl bg-pink-500/10 text-pink-500 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <h3 class="font-display font-bold text-xl mb-3">{{ __('Enterprise Grade Security') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-sm">
                        {{ __('Role-based permissions built on Spatie Laravel Permission. Restrict access dynamically based on role (Platform Staff, Landlords, Managers, and Tenants).') }}
                    </p>
                </div>

                <!-- Card 6 -->
                <div class="group p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/65 dark:border-slate-800/50 hover:border-amber-500/50 hover:shadow-xl transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-all"></div>
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 0c-.822 2.222-2.073 4.222-3.7 6m0 0a17.95 17.95 0 01-4.004-3.5M9.7 15l-3 4.5m12-11.5h3.182a1 1 0 01.707.293l4.818 4.818a1 1 0 010 1.414L20 18H15v-8.5z"></path></svg>
                    </div>
                    <h3 class="font-display font-bold text-xl mb-3">{{ __('Khmer Language Support') }}</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-sm">
                        {{ __('A full Khmer locale is available. Swap the interface language seamlessly, generating documents and panels that feel native to Cambodian operators.') }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section id="testimonials" class="py-24 px-6 bg-slate-100/50 dark:bg-slate-900/30 relative z-10">
            <div class="max-w-7xl mx-auto">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 class="font-display font-bold text-3xl md:text-5xl mb-4">{{ __('Loved by Property Owners') }}</h2>
                    <p class="text-lg text-slate-600 dark:text-slate-400">{{ __('Discover how landlords and managers transformed their operational flows and enhanced tenant satisfaction.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Testimonial 1 -->
                    <div class="p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/50 shadow-sm flex flex-col justify-between">
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed italic mb-8 text-sm">
                            "Before {{ __('ngeay juol') }}, generating invoices and tracking water readings for our 50 units took us days of manual spreadsheet data entry. Now it's done automatically in under 5 minutes. The PDF receipt exports look incredibly professional."
                        </p>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold text-lg">
                                SO
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">Sophea Oum</h4>
                                <p class="text-xs text-slate-500">Landlord, 56 Units (Phnom Penh)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonial 2 -->
                    <div class="p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/50 shadow-sm flex flex-col justify-between">
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed italic mb-8 text-sm">
                            "The unified tenant portal is a game changer. Our tenants can log in, view their invoices, check past utility logs, and download thermal receipts. We've seen a 40% reduction in late-payment disputes."
                        </p>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-cyan-500/10 text-cyan-500 flex items-center justify-center font-bold text-lg">
                                RK
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">Rithy Khem</h4>
                                <p class="text-xs text-slate-500">Property Manager (Siem Reap)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Testimonial 3 -->
                    <div class="p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800/50 shadow-sm flex flex-col justify-between">
                        <p class="text-slate-600 dark:text-slate-400 leading-relaxed italic mb-8 text-sm">
                            "Having full Khmer language localization is what made us choose {{ __('ngeay juol') }}. My staff, who are not fluent in English, can navigate the landlord portal, log utilities, and manage tenants with absolutely no friction."
                        </p>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-blue-500/10 text-blue-500 flex items-center justify-center font-bold text-lg">
                                CN
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">Chantra Nguon</h4>
                                <p class="text-xs text-slate-500">Apartment Owner (Battambang)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-24 px-6 max-w-7xl mx-auto relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="font-display font-bold text-3xl md:text-5xl mb-4">{{ __('Flexible Plans for Every Portfolio') }}</h2>
                <p class="text-lg text-slate-600 dark:text-slate-400">{{ __('Scale your rental business with transparent pricing. Start free, upgrade as you grow.') }}</p>
            </div>

            <!-- Pricing Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <!-- Tier 1 -->
                <div class="p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/60 flex flex-col justify-between">
                    <div>
                        <h3 class="font-display font-bold text-xl mb-2">{{ __('Starter') }}</h3>
                        <p class="text-xs text-slate-500 mb-6">{{ __('Perfect for individual landlords just getting started.') }}</p>
                        <div class="flex items-baseline gap-1 mb-8">
                            <span class="font-display font-extrabold text-4xl">$0</span>
                            <span class="text-slate-500 text-sm">/ forever</span>
                        </div>
                        <ul class="flex flex-col gap-4 text-sm mb-8">
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Up to 5 rental units</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Basic utility tracking</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> PDF invoice exports</li>
                        </ul>
                    </div>
                    <a href="http://127.0.0.1:8000/link" class="w-full py-3 rounded-xl border border-slate-200 dark:border-slate-800 font-semibold text-center hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-sm">
                        {{ __('Get Started') }}
                    </a>
                </div>

                <!-- Tier 2 (Pro) -->
                <div class="p-8 rounded-2xl bg-white dark:bg-slate-900 border-2 border-emerald-500 shadow-xl flex flex-col justify-between relative">
                    <div class="absolute top-0 right-1/2 translate-x-1/2 -translate-y-1/2 px-4 py-1 rounded-full bg-emerald-500 text-white text-xs font-bold uppercase tracking-wider">
                        {{ __('Most Popular') }}
                    </div>
                    <div>
                        <h3 class="font-display font-bold text-xl mb-2">{{ __('Professional') }}</h3>
                        <p class="text-xs text-slate-500 mb-6">{{ __('Designed for expanding property portfolios.') }}</p>
                        <div class="flex items-baseline gap-1 mb-8">
                            <span class="font-display font-extrabold text-4xl">$29</span>
                            <span class="text-slate-500 text-sm">/ month</span>
                        </div>
                        <ul class="flex flex-col gap-4 text-sm mb-8">
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Unlimited rental units</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Advanced utility logs & audits</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Localized PDF & Excel receipt exports</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Priority email & chat support</li>
                        </ul>
                    </div>
                    <a href="http://127.0.0.1:8000/link" class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold text-center shadow-lg shadow-emerald-500/10 hover:shadow-emerald-500/20 transition-all text-sm">
                        {{ __('Start 14-Day Free Trial') }}
                    </a>
                </div>

                <!-- Tier 3 -->
                <div class="p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800/60 flex flex-col justify-between">
                    <div>
                        <h3 class="font-display font-bold text-xl mb-2">{{ __('Enterprise') }}</h3>
                        <p class="text-xs text-slate-500 mb-6">{{ __('Custom features tailored for large operations.') }}</p>
                        <div class="flex items-baseline gap-1 mb-8">
                            <span class="font-display font-extrabold text-4xl">Custom</span>
                        </div>
                        <ul class="flex flex-col gap-4 text-sm mb-8">
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Dedicated dedicated server hosting</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Custom API integrations</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> Dedicated Account Manager</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg> 24/7 Phone support SLA</li>
                        </ul>
                    </div>
                    <a href="mailto:sales@ngeayjuol.com" class="w-full py-3 rounded-xl border border-slate-200 dark:border-slate-800 font-semibold text-center hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-sm">
                        {{ __('Contact Sales') }}
                    </a>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 px-6 max-w-7xl mx-auto relative z-10">
            <div class="rounded-3xl bg-gradient-to-tr from-slate-900 via-slate-850 to-slate-900 border border-slate-800 p-8 md:p-16 text-center relative overflow-hidden shadow-2xl">
                <!-- Decorative Glow inside CTA -->
                <div class="absolute bottom-0 right-0 w-[400px] h-[400px] rounded-full bg-emerald-500/10 glow-bg pointer-events-none"></div>
                <div class="absolute top-0 left-0 w-[400px] h-[400px] rounded-full bg-cyan-500/10 glow-bg pointer-events-none"></div>

                <div class="relative z-10 max-w-2xl mx-auto">
                    <h2 class="font-display font-bold text-3xl md:text-5xl text-white mb-6">{{ __('Ready to Streamline Your Rental Portfolios?') }}</h2>
                    <p class="text-slate-300 text-lg mb-10">{{ __('Join thousands of landlords who trust ngeay juol to run their day-to-day operations seamlessly.') }}</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="http://127.0.0.1:8000/link" class="px-8 py-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold hover:scale-[1.03] active:scale-[0.97] transition-all">
                            {{ __('Start Your Free Trial') }}
                        </a>
                        <a href="{{ route('login') }}" class="px-8 py-4 rounded-xl bg-slate-800 text-white font-bold border border-slate-700 hover:bg-slate-700 transition-all">
                            {{ __('Sign In') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-slate-200/50 dark:border-slate-900/60 bg-white/50 dark:bg-slate-950/50 py-12 px-6 relative z-10 transition-colors duration-300">
            <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
                <!-- Logo & Copyright -->
                <div class="flex items-center gap-3">
                    <img src="{{ asset('Khmer%20House%20Key.svg') }}" alt="{{ __('ngeay juol') }}" class="h-8 w-8 rounded-lg shadow">
                    <span class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        &copy; {{ date('Y') }} {{ __('ngeay juol') }}. All rights reserved.
                    </span>
                </div>

                <!-- Footer Links -->
                <div class="flex items-center gap-6 text-sm text-slate-500 dark:text-slate-400">
                    <a href="#" class="hover:text-emerald-500 transition-colors">{{ __('Privacy Policy') }}</a>
                    <a href="#" class="hover:text-emerald-500 transition-colors">{{ __('Terms of Service') }}</a>
                    <a href="#" class="hover:text-emerald-500 transition-colors">{{ __('Contact Support') }}</a>
                </div>
            </div>
        </footer>

        <!-- Scripts -->
        <script>
            // Theme toggle logic
            const themeToggleBtn = document.getElementById('theme-toggle');
            const sunIcon = document.getElementById('sun-icon');
            const moonIcon = document.getElementById('moon-icon');

            function updateIcons() {
                const isDark = document.documentElement.classList.contains('dark');
                if (isDark) {
                    sunIcon?.classList.remove('hidden');
                    moonIcon?.classList.add('hidden');
                } else {
                    sunIcon?.classList.add('hidden');
                    moonIcon?.classList.remove('hidden');
                }
            }

            // Set initial icons based on document class
            updateIcons();

            themeToggleBtn?.addEventListener('click', function() {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                    localStorage.setItem('color-theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                    localStorage.setItem('color-theme', 'dark');
                }
                updateIcons();
            });

            // Language switcher logic
            const langBtn = document.getElementById('lang-dropdown-btn');
            const langMenu = document.getElementById('lang-dropdown-menu');

            langBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                langMenu.classList.toggle('hidden');
            });

            document.addEventListener('click', function() {
                langMenu.classList.add('hidden');
            });

            // Mobile menu toggle logic
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');

            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        </script>
    </body>
</html>
