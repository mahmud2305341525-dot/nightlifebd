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
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
            function syncNightIcons() {
                const isDark = document.documentElement.classList.contains('dark');
                document.querySelectorAll('.night-icon').forEach(icon => {
                    icon.textContent = isDark ? '☀️' : '🌙';
                });
            }
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            function toggleNightMode() {
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.theme = isDark ? 'dark' : 'light';
                syncNightIcons();
            }
            document.addEventListener('DOMContentLoaded', syncNightIcons);
        </script>
        @stack('head')
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100 transition-colors duration-150">
        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white dark:bg-slate-900 shadow-sm border-b border-slate-200 dark:border-slate-800">
                    <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            @if (session('status') && ! in_array(session('status'), ['profile-updated', 'password-updated', 'verification-link-sent'], true))
                <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 mt-4">
                    <div class="rounded-lg bg-green-50 dark:bg-emerald-950/50 border border-green-200 dark:border-emerald-800 text-green-800 dark:text-emerald-300 px-4 py-3 text-sm">{{ session('status') }}</div>
                </div>
            @endif

            @if (session('error'))
                <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 mt-4">
                    <div class="rounded-lg bg-red-50 dark:bg-rose-950/50 border border-red-200 dark:border-rose-800 text-red-800 dark:text-rose-300 px-4 py-3 text-sm">{{ session('error') }}</div>
                </div>
            @endif

            <main class="flex-1 pb-16 md:pb-0">
                {{ $slot }}
            </main>

            <footer class="text-center text-xs text-slate-500 dark:text-slate-400 py-6 px-4 border-t border-slate-200 dark:border-slate-800/80 mt-auto pb-20 md:pb-6">
                {{ __('Safety information on NightLifeBD is community-sourced and AI-assisted. It is advisory only and does not replace emergency services. In an emergency call 999.') }}
                <div class="mt-1">&copy; NightLifeBD - Error 404 team, Daffodil International University</div>
            </footer>
        </div>

        @include('partials.mobile-bottom-nav')

        @auth
            @include('partials.sos')
        @endauth

        @include('partials.demo-controls')

        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
            }
        </script>
        @stack('scripts')
    </body>
</html>
