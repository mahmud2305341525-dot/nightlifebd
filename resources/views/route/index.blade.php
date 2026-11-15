<x-app-layout>
    @include('partials.leaflet')
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ __('Safer route finder') }}</h2>
        <p class="text-sm text-slate-500">{{ __('Fastest vs safest, scored on verified incidents, community dark spots and nearby police / hospitals.') }}</p>
    </x-slot>

    <div class="max-w-6xl mx-auto p-4 grid lg:grid-cols-3 gap-4">
        <div class="space-y-3">
            <div class="bg-white rounded-xl shadow-sm p-4 space-y-3 text-sm">
                <div>
                    <div class="font-semibold">A · {{ __('Start') }} <span id="a-txt" class="text-xs text-slate-500 font-normal"></span></div>
                    <div class="flex gap-2 mt-1">
                        <button id="btn-a-map" type="button" class="flex-1 border rounded-md py-1.5">{{ __('Click on map') }}</button>
                        <button id="btn-a-gps" type="button" class="flex-1 border rounded-md py-1.5">📍 {{ __('My location') }}</button>
                    </div>
                </div>
                <div>
                    <div class="font-semibold">B · {{ __('Destination') }} <span id="b-txt" class="text-xs text-slate-500 font-normal"></span></div>
                    <select id="b-venue" class="mt-1 w-full border-slate-300 rounded-md text-sm">
                        <option value="">{{ __('Pick a venue or click on the map') }}</option>
                        @foreach ($venues as $v)<option value="{{ $v->lat }},{{ $v->lng }}">{{ $v->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="font-semibold">{{ __('Time of travel') }}</label>
                    <select id="bucket" class="mt-1 w-full border-slate-300 rounded-md text-sm">
                        @foreach ($buckets as $k => $l)<option value="{{ $k }}" @selected($bucket === $k)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <button id="go" type="button" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-md py-2.5 font-semibold">{{ __('Find safest route') }}</button>
                @auth
                    <button id="dark-mode" type="button" class="w-full border border-amber-400 text-amber-700 rounded-md py-1.5">🔦 {{ __('Report a dark spot (then click the map)') }}</button>
                @endauth
                <p id="hint" class="text-xs text-slate-500">{{ __('Step 1: set A. Step 2: set B. Then press the button.') }}</p>
            </div>
            <div id="result" class="hidden bg-white rounded-xl shadow-sm p-4 text-sm space-y-2"></div>
            <p class="text-xs text-slate-500">{{ __('Bangladesh has no open crime dataset, so risk comes from community-verified reports plus OpenStreetMap help points. Routing: OpenRouteService / OSRM. Advisory only.') }}</p>
        </div>
        <div class="lg:col-span-2">
            <div id="map" style="height:560px" class="rounded-xl border border-slate-300 z-0"></div>
        </div>
    </div>

    @push('scripts')
        <script>
            const prefill = @json($prefill);
            const map = NL.map('map');
            let A = null, B = null, mA = null, mB = null, picking = 'A', darkMode = false;
            let layers = [];
            const hint = document.getElementById('hint');
            const icon = (c, t) => L.divIcon({ className: '', html: `<div style="background:${c};color:#fff;width:26px;height:26px;border-radius:50%;text-align:center;line-height:26px;font-weight:700;border:2px solid #fff;box-shadow:0 1px 4px #0006">${t}</div>`, iconSize: [26, 26], iconAnchor: [13, 13] });

            function setA(lat, lng) { A = [lat, lng]; if (mA) map.removeLayer(mA); mA = L.marker(A, { icon: icon('#2563eb', 'A') }).addTo(map); document.getElementById('a-txt').textContent = lat.toFixed(4) + ', ' + lng.toFixed(4); picking = 'B'; hint.textContent = @json(__('Now set B (destination).')); }
            function setB(lat, lng) { B = [lat, lng]; if (mB) map.removeLayer(mB); mB = L.marker(B, { icon: icon('#dc2626', 'B') }).addTo(map); document.getElementById('b-txt').textContent = lat.toFixed(4) + ', ' + lng.toFixed(4); picking = null; hint.textContent = @json(__('Press "Find safest route".')); }

            map.on('click', async e => {
                if (darkMode) {
                    const r = await NL.post('{{ route('dark-spots.store') }}', { lat: e.latlng.lat, lng: e.latlng.lng, note: 'Reported from route page' });
                    if (r.ok) { L.circleMarker(e.latlng, { radius: 8, color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: .8 }).addTo(map).bindPopup(@json(__('Dark spot reported. Thank you!'))); }
                    darkMode = false; document.getElementById('dark-mode').classList.remove('bg-amber-100');
                    return;
                }
                if (picking === 'A' || !A) setA(e.latlng.lat, e.latlng.lng);
                else setB(e.latlng.lat, e.latlng.lng);
            });
            document.getElementById('btn-a-map').onclick = () => { picking = 'A'; hint.textContent = @json(__('Click the map to set A.')); };
            document.getElementById('btn-a-gps').onclick = async () => {
                try { const p = await NL.locate(); setA(p.lat, p.lng); map.setView([p.lat, p.lng], 14); } catch (e) { hint.textContent = @json(__('Could not read your location. Click the map instead.')); }
            };
            document.getElementById('b-venue').onchange = e => { if (e.target.value) { const [la, ln] = e.target.value.split(',').map(Number); setB(la, ln); } };
            const dm = document.getElementById('dark-mode');
            if (dm) dm.onclick = () => { darkMode = true; dm.classList.add('bg-amber-100'); hint.textContent = @json(__('Click the unlit road on the map.')); };

            if (prefill.from_lat) setA(parseFloat(prefill.from_lat), parseFloat(prefill.from_lng));
            if (prefill.to_lat) { setB(parseFloat(prefill.to_lat), parseFloat(prefill.to_lng)); map.setView(B, 14); }
            else map.setView(NL.DHAKA, 12);

            function clear() { layers.forEach(l => map.removeLayer(l)); layers = []; }
            const fmt = r => `${(r.distance_m / 1000).toFixed(1)} km · ${Math.round(r.duration_s / 60)} min`;

            document.getElementById('go').onclick = async () => {
                if (!A || !B) { hint.textContent = @json(__('Please set both A and B first.')); return; }
                const btn = document.getElementById('go'); btn.disabled = true; btn.textContent = @json(__('Scoring routes...'));
                const res = document.getElementById('result');
                const r = await NL.post('{{ route('route.compute') }}', { from_lat: A[0], from_lng: A[1], to_lat: B[0], to_lng: B[1], bucket: document.getElementById('bucket').value });
                btn.disabled = false; btn.textContent = @json(__('Find safest route'));
                res.classList.remove('hidden');
                if (!r.ok) { res.innerHTML = `<p class="text-red-600">${NL.esc((r.data && (r.data.error || r.data.message)) || 'Error')}</p>`; return; }
                const d = r.data; clear();
                d.routes.forEach((rt, i) => {
                    if (i === d.fastest || i === d.safest) return;
                    layers.push(L.polyline(rt.coords, { color: '#94a3b8', weight: 3, opacity: .6, dashArray: '6 8' }).addTo(map));
                });
                const fast = d.routes[d.fastest], safe = d.routes[d.safest];
                if (d.fastest !== d.safest) layers.push(L.polyline(fast.coords, { color: '#64748b', weight: 5, opacity: .9 }).addTo(map).bindPopup(@json(__('Fastest'))));
                const sl = L.polyline(safe.coords, { color: '#16a34a', weight: 6, opacity: .95 }).addTo(map).bindPopup(@json(__('Safest')));
                layers.push(sl);
                safe.haven_list.forEach(h => layers.push(L.circleMarker([h.lat, h.lng], { radius: 7, color: '#2563eb', fillColor: '#60a5fa', fillOpacity: .9 }).addTo(map).bindPopup(NL.esc(h.type + ': ' + h.name))));
                map.fitBounds(sl.getBounds(), { padding: [30, 30] });

                const row = (label, rt, color) => `<div class="rounded-lg border p-3"><div class="font-semibold" style="color:${color}">${label}</div>
                    <div>${fmt(rt)}</div><div class="text-xs text-slate-600">${rt.incidents} {{ __('incident areas') }} · ${rt.dark_spots} {{ __('dark spots') }} · ${rt.havens} {{ __('help points') }}</div>
                    <div class="text-xs text-slate-500">{{ __('risk per km') }}: ${rt.risk_per_km}</div></div>`;
                res.innerHTML = `<p class="font-semibold text-green-700">${NL.esc(d.summary)}</p>
                    <div class="grid grid-cols-2 gap-2">${row(@json(__('Fastest')), fast, '#64748b')}${row(@json(__('Safest')), safe, '#16a34a')}</div>
                    <p class="text-xs text-slate-500">{{ __('Routing by') }} ${NL.esc(d.source)}. {{ __('Routes more than 30% longer than the fastest are never recommended.') }}</p>`;
            };
        </script>
    @endpush
</x-app-layout>
