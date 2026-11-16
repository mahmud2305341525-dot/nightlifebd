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
            
            <div class="space-y-1.5">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('Quick Presets (1-Click Demo):') }}</span>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" onclick="fillPrompt('৩ জন, ৩০০০ টাকা, শান্ত ক্যাফে, ধানমন্ডি', 3000, 3, 'Dhanmondi')" class="text-xs bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-300 border border-slate-200 px-2.5 py-1 rounded-full transition">☕ ধানমন্ডি ক্যাফে (৩ জন, ৩০০০ ৳)</button>
                    <button type="button" onclick="fillPrompt('2 people, 2500 BDT, rooftop view, Gulshan', 2500, 2, 'Gulshan')" class="text-xs bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-300 border border-slate-200 px-2.5 py-1 rounded-full transition">🌆 Gulshan Rooftop (2 ppl, 2500 ৳)</button>
                    <button type="button" onclick="fillPrompt('৪ বন্ধু, ২০০০ টাকা, রাতের খাবার ও চা, পুরান ঢাকা', 2000, 4, 'Old Dhaka')" class="text-xs bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-300 border border-slate-200 px-2.5 py-1 rounded-full transition">🍲 পুরান ঢাকা (৪ জন, ২০০০ ৳)</button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <label>{{ __('Budget (BDT)') }}<input type="number" name="budget" min="100" value="{{ old('budget') }}" class="w-full border-slate-300 rounded-md"></label>
                <label>{{ __('People') }}<input type="number" name="group" min="1" max="20" value="{{ old('group') }}" class="w-full border-slate-300 rounded-md"></label>
                <label>{{ __('Area') }}
                    <select name="area" class="w-full border-slate-300 rounded-md"><option value="">{{ __('Any') }}</option>@foreach ($areas as $a)<option @selected(old('area') === $a)>{{ $a }}</option>@endforeach</select>
                </label>
                <label>{{ __('Start hour (0-23)') }}<input type="number" name="hour" min="0" max="23" value="{{ old('hour', 20) }}" class="w-full border-slate-300 rounded-md"></label>
            </div>
            <button id="plan-btn" class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 text-white rounded-md py-2.5 font-semibold transition">🌙 {{ __('Plan my night') }}</button>
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
        @php
            $stopsData = array_map(function ($s) {
                return [
                    'n' => $s['venue']->name,
                    'lat' => (float) $s['venue']->lat,
                    'lng' => (float) $s['venue']->lng,
                    'b' => $s['badge'],
                ];
            }, $result['plan']['stops']);
        @endphp
        @push('scripts')
            <script>
                const stops = {!! json_encode($stopsData) !!};
                if (stops && stops.length > 0) {
                    const map = NL.map('map', [stops[0].lat, stops[0].lng], 13);
                    stops.forEach((s, i) => NL.dot(map, s.lat, s.lng, NL.colors[s.b] || NL.colors.unrated, (i + 1) + '. ' + NL.esc(s.n)));
                    if (stops.length > 1) {
                        L.polyline(stops.map(s => [s.lat, s.lng]), { color: '#4f46e5', dashArray: '6 8' }).addTo(map);
                        map.fitBounds(stops.map(s => [s.lat, s.lng]), { padding: [30, 30] });
                    }
                }
            </script>
        @endpush
    @endif
    @push('scripts')
    <script>
        window.fillPrompt = function (text, budget, group, area) {
            const ta = document.querySelector('textarea[name="text"]');
            if (ta) ta.value = text;
            const b = document.querySelector('input[name="budget"]');
            if (b) b.value = budget;
            const g = document.querySelector('input[name="group"]');
            if (g) g.value = group;
            const sel = document.querySelector('select[name="area"]');
            if (sel) {
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].text.toLowerCase().includes(area.toLowerCase())) {
                        sel.selectedIndex = i;
                        break;
                    }
                }
            }
        };

        (function () {
            const form = document.querySelector('form[action="{{ route('planner.generate') }}"]');
            const btn  = document.getElementById('plan-btn');
            if (!form || !btn) return;
            form.addEventListener('submit', () => {
                btn.disabled = true;
                btn.innerHTML = '<svg class="inline w-4 h-4 mr-2 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg> {{ __('Planning your night…') }}';
            });
            // Restore on back-navigate (bfcache)
            window.addEventListener('pageshow', () => { btn.disabled = false; btn.innerHTML = '🌙 {{ __('Plan my night') }}'; });
        })();
    </script>
    @endpush
</x-app-layout>
