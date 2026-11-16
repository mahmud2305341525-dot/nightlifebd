<x-app-layout>
    <div class="min-h-[60vh] flex flex-col items-center justify-center text-center p-6">
        <div class="w-16 h-16 rounded-2xl bg-indigo-100 text-indigo-800 flex items-center justify-center text-3xl font-bold mb-4 shadow-sm">
            🗺️
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">{{ __('404 — Page Not Found') }}</h1>
        <p class="text-sm text-slate-500 max-w-md mt-2">
            {{ __('The page or safety record you are looking for does not exist or has been moved.') }}
        </p>
        <div class="mt-6 flex gap-3">
            <a href="{{ route('home') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-4 py-2.5 rounded-xl transition shadow-sm">
                {{ __('Return Home') }}
            </a>
            <a href="{{ route('venues.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold text-xs px-4 py-2.5 rounded-xl transition border border-slate-200">
                {{ __('Browse Venues') }}
            </a>
        </div>
    </div>
</x-app-layout>

