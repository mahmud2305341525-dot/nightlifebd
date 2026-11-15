<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0f172a">
        <title>{{ config('app.name', 'NightLifeBD') }}</title>

        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/png" href="/icons/icon-192.png">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-900">
        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white shadow-sm">
                    <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            @if (session('status') && ! in_array(session('status'), ['profile-updated', 'password-updated', 'verification-link-sent'], true))
                <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 mt-4">
                    <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('status') }}</div>
                </div>
            @endif

            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="text-center text-xs text-slate-500 py-6 px-4">
                {{ __('Safety information on NightLifeBD is community-sourced and AI-assisted. It is advisory only and does not replace emergency services. In an emergency call 999.') }}
                <div class="mt-1">&copy; NightLifeBD - Error 404 team, Daffodil International University</div>
            </footer>
        </div>

        @auth
            @include('partials.sos')
        @endauth

        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
            }
        </script>
        @stack('scripts')
    </body>
</html>
