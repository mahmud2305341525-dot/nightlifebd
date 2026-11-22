<x-app-layout>
    <div class="max-w-xl mx-auto p-4 space-y-4">
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ __('Live trip sharing & ride check-in') }}</h1>
        @unless ($hasContacts)
            <div class="rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 p-3 text-sm text-amber-900 dark:text-amber-200">{{ __('Add an emergency contact first so someone is told about your trip.') }} <a class="underline text-amber-700 dark:text-amber-400 font-semibold" href="{{ route('contacts.index') }}">{{ __('Add contacts') }}</a></div>
        @endunless
        <form method="POST" action="{{ route('trips.store') }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 space-y-3 text-sm">
            @csrf
            <div class="grid grid-cols-2 gap-2">
                @foreach (['trip' => __('Walking / own transport'), 'ride' => __('CNG / rickshaw / ride-share')] as $k => $l)
                    <label class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 cursor-pointer has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-950/50 text-slate-800 dark:text-slate-200"><input type="radio" name="kind" value="{{ $k }}" class="me-2 text-indigo-600 focus:ring-indigo-500" @checked(old('kind', 'trip') === $k)>{{ $l }}</label>
                @endforeach
            </div>
            <input name="destination" placeholder="{{ __('Destination') }}" value="{{ old('destination') }}" class="w-full border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-md text-sm">
            <input name="vehicle_plate" placeholder="{{ __('Vehicle plate (rides) e.g. DHAKA METRO-GA 12-3456') }}" value="{{ old('vehicle_plate') }}" class="w-full border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-md text-sm">
            <div class="grid grid-cols-2 gap-2">
                <input name="share_pin" inputmode="numeric" maxlength="8" placeholder="{{ __('Optional 4-8 digit PIN') }}" value="{{ old('share_pin') }}" class="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 rounded-md text-sm">
                <input name="max_views" type="number" min="1" max="100" placeholder="{{ __('Optional max views') }}" value="{{ old('max_views') }}" class="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 rounded-md text-sm">
            </div>
            <label class="block text-slate-800 dark:text-slate-200">{{ __('Share for') }}
                <select name="duration" class="mt-1 w-full border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-md text-sm">
                    @foreach ([15, 30, 45, 60, 90, 120, 180] as $m)<option value="{{ $m }}" @selected(old('duration', 60) == $m)>{{ $m }} {{ __('minutes') }}</option>@endforeach
                </select>
            </label>
            <x-input-error :messages="$errors->all()" />
            <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-md py-2.5 font-semibold transition">📍 {{ __('Start & notify my contacts') }}</button>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Your phone sends its location every 10 seconds while this page stays open. The link expires automatically and old locations are deleted after 72 hours.') }}</p>
        </form>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4">
            <h2 class="font-semibold text-slate-900 dark:text-slate-100 mb-2">{{ __('Recent trips') }}</h2>
            @forelse ($trips as $t)
                <div class="flex justify-between text-sm py-1 border-b border-slate-100 dark:border-slate-800 last:border-0 text-slate-700 dark:text-slate-300">
                    <span>{{ $t->created_at->format('d M h:i A') }} · {{ $t->destination ?: ucfirst($t->kind) }} {{ $t->vehicle_plate ? '· '.$t->vehicle_plate : '' }}</span>
                    @if ($t->isActive())<a class="text-indigo-600 dark:text-indigo-400 underline font-semibold" href="{{ route('trips.show', $t->token) }}">{{ __('open') }}</a>@else<span class="text-slate-400 dark:text-slate-500">{{ __('ended') }}</span>@endif
                </div>
            @empty
                <div class="p-6 text-center text-slate-500 space-y-1.5">
                    <div class="text-3xl">🚗</div>
                    <div class="font-semibold text-xs text-slate-700 dark:text-slate-300">{{ __('No trip history yet') }}</div>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 max-w-xs mx-auto">{{ __('Start a live trip or ride check-in above to share real-time GPS tracking with your trusted circle.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
