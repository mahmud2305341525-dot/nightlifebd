<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.events.index') }}" class="p-2 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition text-sm">
                ←
            </a>
            <div>
                <h2 class="font-bold text-xl text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span>🎸</span>
                    <span>{{ __('Publish New Night Event / Concert') }}</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Create a new live gig or concert with custom ticket tiers and NightLifeBD venue safety linkage.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <form method="POST" action="{{ route('admin.events.store') }}" class="space-y-6">
            @csrf

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-3">
                    {{ __('1. Basic Event Information') }}
                </h3>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="font-semibold text-slate-700 dark:text-slate-300 block mb-1">{{ __('Event Title') }} *</label>
                        <input type="text" name="title" required value="{{ old('title') }}" placeholder="e.g. Dhaka Indie Acoustic Night 2026"
                               class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="font-semibold text-slate-700 dark:text-slate-300 block mb-1">{{ __('Category') }} *</label>
                            <select name="category" required class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach ($categories as $catKey => $catLabel)
                                    <option value="{{ $catKey }}" @selected(old('category') === $catKey)>{{ $catLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="font-semibold text-slate-700 dark:text-slate-300 block mb-1">{{ __('Area') }} *</label>
                            <input type="text" name="area" required value="{{ old('area', 'Bashundhara') }}" placeholder="e.g. Dhanmondi, Gulshan, Bashundhara"
                                   class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="font-semibold text-slate-700 dark:text-slate-300 block mb-1">{{ __('Linked NightLifeBD Venue (Optional)') }}</label>
                            <select name="venue_id" class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- {{ __('No Linked Venue (Standalone Venue)') }} --</option>
                                @foreach ($venues as $venue)
                                    <option value="{{ $venue->id }}" @selected(old('venue_id') == $venue->id)>{{ $venue->name }} ({{ $venue->area }})</option>
                                @endforeach
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1">{{ __('Linking automatically imports the venue live safety score & GPS location.') }}</p>
                        </div>

                        <div>
                            <label class="font-semibold text-slate-700 dark:text-slate-300 block mb-1">{{ __('Address / Location') }} *</label>
                            <input type="text" name="address" required value="{{ old('address') }}" placeholder="e.g. Road 11, Block D, Banani"
                                   class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="font-semibold text-slate-700 dark:text-slate-300 block mb-1">{{ __('Starts At') }} *</label>
                            <input type="datetime-local" name="starts_at" required value="{{ old('starts_at', now()->addDays(2)->format('Y-m-d\TH:i')) }}"
                                   class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="font-semibold text-slate-700 dark:text-slate-300 block mb-1">{{ __('Ends At') }} *</label>
                            <input type="datetime-local" name="ends_at" required value="{{ old('ends_at', now()->addDays(2)->addHours(4)->format('Y-m-d\TH:i')) }}"
                                   class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="font-semibold text-slate-700 dark:text-slate-300 block mb-1">{{ __('Description & Artist Lineup') }} *</label>
                        <textarea name="description" rows="4" required class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Provide full details about performers, safety measures, entry gates…">{{ old('description') }}</textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_featured" id="is_featured" value="1" @checked(old('is_featured')) class="rounded text-indigo-600 focus:ring-indigo-500">
                        <label for="is_featured" class="text-xs text-slate-700 dark:text-slate-300 font-semibold cursor-pointer">
                            ⭐ {{ __('Mark as Featured Event (promoted on top banner)') }}
                        </label>
                    </div>
                </div>
            </div>

            {{-- 2. Ticket Tiers --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100">
                        {{ __('2. Ticket Tiers & Capacity') }}
                    </h3>
                </div>

                <div class="space-y-3" id="tiers-container">
                    {{-- Tier 1: General --}}
                    <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40 grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                        <div class="sm:col-span-2">
                            <label class="font-semibold block mb-1 text-slate-700 dark:text-slate-300">{{ __('Tier Name') }}</label>
                            <input type="text" name="tiers[0][name]" value="General Admission" required class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                        </div>
                        <div>
                            <label class="font-semibold block mb-1 text-slate-700 dark:text-slate-300">{{ __('Price (৳ / BDT)') }}</label>
                            <input type="number" step="0.01" min="0" name="tiers[0][price]" value="500" required class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                        </div>
                        <div>
                            <label class="font-semibold block mb-1 text-slate-700 dark:text-slate-300">{{ __('Total Capacity') }}</label>
                            <input type="number" min="1" name="tiers[0][capacity]" value="200" required class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                            <input type="hidden" name="tiers[0][max_per_user]" value="5">
                        </div>
                    </div>

                    {{-- Tier 2: VIP --}}
                    <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40 grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                        <div class="sm:col-span-2">
                            <label class="font-semibold block mb-1 text-slate-700 dark:text-slate-300">{{ __('Tier Name') }}</label>
                            <input type="text" name="tiers[1][name]" value="VIP Front Row" required class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                        </div>
                        <div>
                            <label class="font-semibold block mb-1 text-slate-700 dark:text-slate-300">{{ __('Price (৳ / BDT)') }}</label>
                            <input type="number" step="0.01" min="0" name="tiers[1][price]" value="1500" required class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                        </div>
                        <div>
                            <label class="font-semibold block mb-1 text-slate-700 dark:text-slate-300">{{ __('Total Capacity') }}</label>
                            <input type="number" min="1" name="tiers[1][capacity]" value="50" required class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                            <input type="hidden" name="tiers[1][max_per_user]" value="2">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('admin.events.index') }}" class="px-4 py-2.5 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md transition">
                    {{ __('Publish Concert / Event') }} →
                </button>
            </div>
        </form>

    </div>
</x-app-layout>
