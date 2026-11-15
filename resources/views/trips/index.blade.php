<x-app-layout>
    <div class="max-w-xl mx-auto p-4 space-y-4">
        <h1 class="text-xl font-bold">{{ __('Live trip sharing & ride check-in') }}</h1>
        @unless ($hasContacts)
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm">{{ __('Add an emergency contact first so someone is told about your trip.') }} <a class="underline" href="{{ route('contacts.index') }}">{{ __('Add contacts') }}</a></div>
        @endunless
        <form method="POST" action="{{ route('trips.store') }}" class="bg-white rounded-xl shadow-sm p-4 space-y-3 text-sm">
            @csrf
            <div class="grid grid-cols-2 gap-2">
                @foreach (['trip' => __('Walking / own transport'), 'ride' => __('CNG / rickshaw / ride-share')] as $k => $l)
                    <label class="border rounded-lg p-3 cursor-pointer has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50"><input type="radio" name="kind" value="{{ $k }}" class="me-2" @checked(old('kind', 'trip') === $k)>{{ $l }}</label>
                @endforeach
            </div>
            <input name="destination" placeholder="{{ __('Destination') }}" value="{{ old('destination') }}" class="w-full border-slate-300 rounded-md">
            <input name="vehicle_plate" placeholder="{{ __('Vehicle plate (rides) e.g. DHAKA METRO-GA 12-3456') }}" value="{{ old('vehicle_plate') }}" class="w-full border-slate-300 rounded-md">
            <label class="block">{{ __('Share for') }}
                <select name="duration" class="mt-1 w-full border-slate-300 rounded-md">
                    @foreach ([15, 30, 45, 60, 90, 120, 180] as $m)<option value="{{ $m }}" @selected(old('duration', 60) == $m)>{{ $m }} {{ __('minutes') }}</option>@endforeach
                </select>
            </label>
            <x-input-error :messages="$errors->all()" />
            <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-md py-2.5 font-semibold">📍 {{ __('Start & notify my contacts') }}</button>
            <p class="text-xs text-slate-500">{{ __('Your phone sends its location every 10 seconds while this page stays open. The link expires automatically and old locations are deleted after 72 hours.') }}</p>
        </form>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <h2 class="font-semibold mb-2">{{ __('Recent trips') }}</h2>
            @forelse ($trips as $t)
                <div class="flex justify-between text-sm py-1 border-b last:border-0">
                    <span>{{ $t->created_at->format('d M h:i A') }} · {{ $t->destination ?: ucfirst($t->kind) }} {{ $t->vehicle_plate ? '· '.$t->vehicle_plate : '' }}</span>
                    @if ($t->isActive())<a class="text-indigo-600 underline" href="{{ route('trips.show', $t->token) }}">{{ __('open') }}</a>@else<span class="text-slate-400">{{ __('ended') }}</span>@endif
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('No trips yet.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
