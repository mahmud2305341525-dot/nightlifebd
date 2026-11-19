<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
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
    </head>
    <body class="font-sans text-gray-900 dark:text-gray-100 antialiased bg-gray-100 dark:bg-slate-950 transition-colors duration-150 relative">
        <div class="absolute top-4 right-4 flex items-center gap-2">
            <button type="button" onclick="toggleNightMode()" class="p-1.5 rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition text-xs flex items-center gap-1 cursor-pointer" title="{{ __('Toggle Night Vision Mode') }}" aria-label="{{ __('Toggle Night Vision Mode') }}">
                <span class="night-icon">🌙</span>
            </button>
            <div class="text-xs border border-slate-300 dark:border-slate-700 rounded-md overflow-hidden flex bg-white dark:bg-slate-900">
                <a href="{{ route('lang', 'en') }}" class="px-2 py-1 {{ app()->getLocale() === 'en' ? 'bg-slate-900 dark:bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300' }}">EN</a>
                <a href="{{ route('lang', 'bn') }}" class="px-2 py-1 {{ app()->getLocale() === 'bn' ? 'bg-slate-900 dark:bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300' }}">বাং</a>
            </div>
        </div>
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div>
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-indigo-600 dark:text-indigo-400" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-slate-900 shadow-md border border-slate-200 dark:border-slate-800 overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
