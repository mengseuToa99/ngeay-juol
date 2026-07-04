<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('ngeay juol') }}</title>
    <link rel="icon" href="{{ asset('Khmer%20House%20Key.svg') }}" type="image/svg+xml">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <header class="bg-emerald-600 text-white shadow">
        <div class="mx-auto flex max-w-2xl items-center justify-between px-4 py-3">
            <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-2 text-lg font-bold">
                <img src="{{ asset('Khmer%20House%20Key.svg') }}" alt="{{ __('ngeay juol') }}" class="h-7 w-7 rounded">
                {{ __('ngeay juol') }}
            </a>
            @auth
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button class="rounded bg-emerald-700 px-3 py-1.5 text-sm font-medium hover:bg-emerald-800">
                        {{ __('Log out') }}
                    </button>
                </form>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-2xl px-4 py-6">
        @yield('content')
    </main>

    <footer class="py-6 text-center text-xs text-slate-400">{{ __('ngeay juol') }} · {{ __('Tenant portal') }}</footer>
</body>
</html>
