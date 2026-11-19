<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <section class="space-y-4">
                        <header>
                            <h2 class="text-lg font-medium text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                <span>📦</span> {{ __('Data Portability & Privacy') }}
                            </h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                {{ __('Download a portable JSON archive of your personal account data, emergency contacts, report history, and trip logs.') }}
                            </p>
                        </header>
                        <a href="{{ route('profile.export') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600 text-white font-semibold text-xs rounded-md shadow-sm transition">
                            <span>⬇️</span> {{ __('Download My Data (JSON)') }}
                        </a>
                    </section>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
