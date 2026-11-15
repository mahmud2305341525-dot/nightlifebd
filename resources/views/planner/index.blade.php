<x-app-layout>
    @include('partials.leaflet')
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ __('AI Night Planner') }}</h2>
        <p class="text-sm text-slate-500">{{ __('Describe your night in Bangla or English. The plan uses only venues from our database and never picks a caution-rated place.') }}</p>
    </x-slot>

    <div class="max-w-5xl mx-auto p-4 grid md:grid-cols-2 gap-4">
        <form method="POST" action="{{ route('planner.generate') }}" class="bg-white rounded-xl shadow-sm p-4 space-y-3 text-sm h-fit">
            @csrf
            <textarea name="text" rows="3" maxlength="500" class="w-full border-slate-300 rounded-md" placeholder="৩ জন, ৩০০০ টাকা, শান্ত জায়গা, ধানমন্ডি">{{ old('text') }}</textarea>
            <div class="grid grid-cols-2 gap-2">
                <label>{{ __('Budget (BDT)') }}<input type="number" name="budget" min="100" value="{{ old('budget') }}" class="w-full border-slate-300 rounded-md"></label>
                <label>{{ __('People') }}<input type="number" name="group" min="1" max="20" value="{{ old('group') }}" class="w-full border-slate-300 rounded-md"></label>
                <label>{{ __('Area') }}
                    <select name="area" class="w-full border-slate-300 rounded-md"><option value="">{{ __('Any') }}</option>@foreach ($areas as $a)<option @selected(old('area') === $a)>{{ $a }}</option>@endforeach</select>
                </label>
                <label>{{ __('Start hour (0-23)') }}<input type="number" name="hour" min="0" max="23" value="{{ old('hour', 20) }}" class="w-full border-slate-300 rounded-md"></label>
            </div>
            <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-md py-2.5 font-semibold">🌙 {{ __('Plan my night') }}</button>
            <p class="text-xs text-slate-500">{{ __('Fields you leave empty are understood from your sentence. Costs are estimates per person: budget 300, mid 700, high 1500 BDT.') }}</p>
        </form>

        <div>
            @if ($result)
                @if ($result['warning'])
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">{{ $result['warning'] }}</div>
                @else
                    @php $p = $result['plan']; $f = $result['filters']; @endphp
                    <div class="bg-white rounded-xl shadow-sm p-4 space-y-3">
                        <div class="flex justify-between items-start gap-2">
                            <h3 class="font-bold text-lg">{{ $p['title'] }}</h3>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">AI-assisted · {{ $result['source'] }}</span>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ $f['group'] }} {{ __('people') }}
                            @if ($f['budget']) · {{ __('budget') }} ৳{{ number_format($f['budget']) }} @endif
                            @if ($f['area']) · {{ $f['area'] }} @endif
                            · {{ \App\Support\TimeBucket::label($p['bucket']) }}
                        </p>
                        <ol class="space-y-3">
                            @foreach ($p['stops'] as $i => $s)
                                <li class="flex gap-3">
                                    <div class="shrink-0 w-8 h-8 rounded-full bg-slate-900 text-white text-sm flex items-center justify-center">{{ $i + 1 }}</div>
                                    <div class="text-sm">
                                        <div class="font-semibold"><a class="hover:underline" href="{{ route('venues.show', $s['venue']) }}">{{ $s['venue']->name }}</a> <x-badge :badge="$s['badge']" /></div>
                                        <div class="text-slate-500">{{ $s['arrive'] }} · {{ ucfirst($s['venue']->category) }} · {{ $s['venue']->area }} · ~৳{{ number_format($s['cost']) }}</div>
                                        <div class="text-slate-700">{{ $s['reason'] }}</div>
                                        @if ($i > 0)
                                            @php $prev = $p['stops'][$i - 1]['venue']; @endphp
                                            <a class="text-xs text-indigo-600 underline" href="{{ route('route.index', ['from_lat' => $prev->lat, 'from_lng' => $prev->lng, 'to_lat' => $s['venue']->lat, 'to_lng' => $s['venue']->lng]) }}">🛣️ {{ __('Safest route from previous stop') }}</a>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        <div class="text-sm font-semibold border-t pt-2">{{ __('Estimated total') }}: ৳{{ number_format($p['total_cost']) }}</div>
                        @if ($p['tip'])<p class="text-xs text-slate-600">💡 {{ $p['tip'] }}</p>@endif
                        <div id="map" style="height:260px" class="rounded-lg border border-slate-300 z-0"></div>
                    </div>
                @endif
            @else
                <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-500 text-sm">{{ __('Your plan will appear here.') }}</div>
            @endif
        </div>
    </div>

    @if ($result && ! $result['warning'])
        @push('scripts')
            <script>
                const stops = @json(collect($result['plan']['stops'])->map(fn ($s) => ['n' => $s['venue']->name, 'lat' => $s['venue']->lat, 'lng' => $s['venue']->lng, 'b' => $s['badge']])->values());
                const map = NL.map('map', [stops[0].lat, stops[0].lng], 13);
                stops.forEach((s, i) => NL.dot(map, s.lat, s.lng, NL.colors[s.b] || NL.colors.unrated, (i + 1) + '. ' + NL.esc(s.n)));
                if (stops.length > 1) { L.polyline(stops.map(s => [s.lat, s.lng]), { color: '#4f46e5', dashArray: '6 8' }).addTo(map); map.fitBounds(stops.map(s => [s.lat, s.lng]), { padding: [30, 30] }); }
            </script>
        @endpush
    @endif
</x-app-layout>
