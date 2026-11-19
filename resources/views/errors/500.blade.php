<x-app-layout>
    <div class="min-h-[60vh] flex flex-col items-center justify-center text-center p-6">
        <div class="w-16 h-16 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center text-3xl font-bold mb-4 shadow-sm">
            ⚠️
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">{{ __('500 — Temporary Server Error') }}</h1>
        <p class="text-sm text-slate-500 max-w-md mt-2">
            {{ __('Something went wrong on our end. For security, sensitive diagnostic details are hidden. Please try again shortly or contact support.') }}
        </p>
        <div class="mt-6 flex gap-3">
            <a href="{{ route('home') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-4 py-2.5 rounded-xl transition shadow-sm">
                {{ __('Return Home') }}
            </a>
            <a href="{{ route('sos.index') }}" class="bg-red-600 hover:bg-red-500 text-white font-semibold text-xs px-4 py-2.5 rounded-xl transition shadow-sm">
                {{ __('Emergency SOS Hub') }}
            </a>
        </div>
    </div>
</x-app-layout>

