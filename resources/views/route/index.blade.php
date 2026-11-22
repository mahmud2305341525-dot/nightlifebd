<x-app-layout>
    @include('partials.leaflet')
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100">{{ __('Safer route finder') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Multi-objective comparison: Fastest vs Safest vs Well-Lit, scored on verified incidents, dark spots and nearby help points.') }}</p>
    </x-slot>

    @if (!empty($event))
        <div class="max-w-7xl mx-auto px-4 pt-3 pb-0">
            <div class="rounded-xl bg-gradient-to-r from-indigo-900 to-slate-900 text-white p-4 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border border-indigo-700/80">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🎸</span>
                    <div>
                        <div class="text-[10px] uppercase font-bold tracking-wider text-indigo-300">{{ __('Concert Safe Route Navigation') }}</div>
                        <div class="font-bold text-sm text-white">{{ $event->title }}</div>
                        <div class="text-xs text-indigo-200">📍 {{ $prefill['venue_name'] ?? ($event->venue ? $event->venue->name : $event->address) }} ({{ $event->area }})</div>
                    </div>
                </div>
                <a href="{{ route('events.show', $event->slug) }}" class="px-3.5 py-1.5 rounded-lg bg-indigo-600/80 hover:bg-indigo-600 text-white text-xs font-semibold transition border border-indigo-400/30 flex items-center gap-1 shrink-0">
                    ← {{ __('Back to Concert') }}
                </a>
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto p-4 grid lg:grid-cols-3 gap-4">
        <div class="space-y-3">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 space-y-3 text-sm">
                @if(!empty($prefill['venue_name']))
                <div class="-mt-1 mb-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 px-3 py-2 flex items-center gap-2">
                    <span class="text-lg">🏙️</span>
                    <div class="min-w-0">
                        <div class="text-xs text-indigo-500 dark:text-indigo-400 font-semibold uppercase tracking-wide">{{ __('Destination') }}</div>
                        <div class="font-bold text-indigo-900 dark:text-indigo-200 truncate">{{ $prefill['venue_name'] }}</div>
                    </div>
                </div>
                @endif
                <div>
                    <div class="font-semibold text-slate-800 dark:text-slate-100">A · {{ __('Start') }} <span id="a-txt" class="text-xs text-slate-500 dark:text-slate-400 font-normal"></span></div>
                    <div class="flex gap-2 mt-1">
                        <button id="btn-a-map" type="button" class="flex-1 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-md py-1.5 transition">{{ __('Click on map') }}</button>
                        <button id="btn-a-gps" type="button" class="flex-1 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-md py-1.5 transition">📍 {{ __('My location') }}</button>
                    </div>
                </div>
                <div>
                    <div class="font-semibold text-slate-800 dark:text-slate-100">B · {{ __('Destination') }} <span id="b-txt" class="text-xs text-slate-500 dark:text-slate-400 font-normal">{{ !empty($prefill['venue_name']) ? $prefill['venue_name'] : '' }}</span></div>
                    <select id="b-venue" class="mt-1 w-full border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-md text-sm">
                        @if(!empty($prefill['to_lat']) && !empty($prefill['to_lng']))
                            <option value="{{ $prefill['to_lat'] }},{{ $prefill['to_lng'] }}" selected>
                                {{ $prefill['venue_name'] ?? __('Selected Destination') }} {{ !empty($event) ? '('.__('Concert Venue').')' : '' }}
                            </option>
                        @else
                            <option value="">{{ __('Pick a venue or click on the map') }}</option>
                        @endif
                        @foreach ($venues as $v)
                            @if(empty($prefill['venue_name']) || $v->name !== $prefill['venue_name'])
                                <option value="{{ $v->lat }},{{ $v->lng }}">{{ $v->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Travel Mode') }}</label>
                    <div class="grid grid-cols-2 gap-2 mt-1">
                        <button type="button" id="btn-mode-driving" class="mode-btn py-1.5 px-3 rounded-md text-xs font-semibold border border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center gap-1.5 transition">
                            <span>🚗</span> <span>{{ __('Driving') }}</span>
                        </button>
                        <button type="button" id="btn-mode-walking" class="mode-btn py-1.5 px-3 rounded-md text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center justify-center gap-1.5 transition">
                            <span>🚶</span> <span>{{ __('Walking') }}</span>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="font-semibold text-slate-800 dark:text-slate-100">{{ __('Time of travel') }}</label>
                    <select id="bucket" class="mt-1 w-full border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-md text-sm">
                        @foreach ($buckets as $k => $l)<option value="{{ $k }}" @selected($bucket === $k)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <button id="go" type="button" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-md py-2.5 font-semibold transition-all">{{ __('Find safest route') }}</button>
                @auth
                    <button id="dark-mode" type="button" class="w-full border border-amber-400 dark:border-amber-600 bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 rounded-md py-1.5 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition">🔦 {{ __('Report a dark spot (then click the map)') }}</button>
                @endauth
                <p id="hint" class="text-xs {{ !empty($prefill['venue_name']) ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-slate-500 dark:text-slate-400' }}">
                    {{ !empty($prefill['venue_name']) ? __('📍 Detecting your location as starting point…') : __('Step 1: set A. Step 2: set B. Then press the button.') }}
                </p>
            </div>

            {{-- Pareto Comparison Panel --}}
            <div id="result" class="hidden space-y-3"></div>

            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Routing providers are advisory only. Production uses the configured provider and does not silently replace walking routes with driving geometry. Map data © OpenStreetMap contributors.') }}</p>
        </div>
        <div class="lg:col-span-2">
            <div id="map" style="height:620px" class="rounded-xl border border-slate-300 dark:border-slate-700 z-0"></div>
        </div>
    </div>

    @push('scripts')
        <script>
            const prefill = @json($prefill);
            const map = NL.map('map');
            let A = null, B = null, mA = null, mB = null, picking = 'A', darkMode = false, travelMode = 'driving';
            let layers = [], routeData = null, routeLayers = {};
            const hint = document.getElementById('hint');
            const icon = (c, t) => L.divIcon({ className: '', html: `<div style="background:${c};color:#fff;width:26px;height:26px;border-radius:50%;text-align:center;line-height:26px;font-weight:700;border:2px solid #fff;box-shadow:0 1px 4px #0006">${t}</div>`, iconSize: [26, 26], iconAnchor: [13, 13] });

            const btnDrive = document.getElementById('btn-mode-driving');
            const btnWalk = document.getElementById('btn-mode-walking');
            if (btnDrive && btnWalk) {
                btnDrive.onclick = () => {
                    travelMode = 'driving';
                    btnDrive.className = 'mode-btn py-1.5 px-3 rounded-md text-xs font-semibold border border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center gap-1.5 transition';
                    btnWalk.className = 'mode-btn py-1.5 px-3 rounded-md text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center justify-center gap-1.5 transition';
                };
                btnWalk.onclick = () => {
                    travelMode = 'walking';
                    btnWalk.className = 'mode-btn py-1.5 px-3 rounded-md text-xs font-semibold border border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center gap-1.5 transition';
                    btnDrive.className = 'mode-btn py-1.5 px-3 rounded-md text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center justify-center gap-1.5 transition';
                };
            }

            function setA(lat, lng) { A = [lat, lng]; if (mA) map.removeLayer(mA); mA = L.marker(A, { icon: icon('#2563eb', 'A') }).addTo(map); document.getElementById('a-txt').textContent = lat.toFixed(4) + ', ' + lng.toFixed(4); picking = 'B'; hint.textContent = @json(__('Now set B (destination).')); }
            function setB(lat, lng) { B = [lat, lng]; if (mB) map.removeLayer(mB); mB = L.marker(B, { icon: icon('#dc2626', 'B') }).addTo(map); document.getElementById('b-txt').textContent = lat.toFixed(4) + ', ' + lng.toFixed(4); picking = null; hint.textContent = @json(__('Press "Find safest route".')); }

            map.on('click', async e => {
                if (darkMode) {
                    const r = await NL.post('{{ route('dark-spots.store') }}', { lat: e.latlng.lat, lng: e.latlng.lng, note: 'Reported from route page' });
                    if (r.ok) { L.circleMarker(e.latlng, { radius: 8, color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: .8 }).addTo(map).bindPopup(@json(__('Dark spot reported. Thank you!'))); }
                    darkMode = false;
                    const dmb = document.getElementById('dark-mode');
                    if (dmb) { dmb.className = 'w-full border border-amber-400 dark:border-amber-600 bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 rounded-md py-1.5 hover:bg-amber-100 transition'; dmb.textContent = @json(__('🔦 Report a dark spot (then click the map)')); }
                    return;
                }
                if (picking === 'A') setA(e.latlng.lat, e.latlng.lng);
                else if (picking === 'B') setB(e.latlng.lat, e.latlng.lng);
            });

            document.getElementById('btn-a-map').onclick = () => { picking = 'A'; hint.textContent = @json(__('Click the map to place A.')); };
            document.getElementById('btn-a-gps').onclick = () => {
                hint.textContent = @json(__('Acquiring GPS…'));
                navigator.geolocation.getCurrentPosition(
                    p => setA(p.coords.latitude, p.coords.longitude),
                    err => { hint.textContent = @json(__('GPS failed: ')) + err.message; },
                    { enableHighAccuracy: true, timeout: 8000 }
                );
            };

            const dmb = document.getElementById('dark-mode');
            if (dmb) {
                dmb.onclick = () => {
                    darkMode = !darkMode;
                    dmb.className = darkMode ? 'w-full bg-amber-500 text-white rounded-md py-1.5 font-bold transition' : 'w-full border border-amber-400 dark:border-amber-600 bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 rounded-md py-1.5 hover:bg-amber-100 transition';
                    dmb.textContent = darkMode ? @json(__('Now click the dark spot on the map')) : @json(__('🔦 Report a dark spot (then click the map)'));
                };
            }

            document.getElementById('b-venue').onchange = e => {
                if (!e.target.value) return;
                const [lat, lng] = e.target.value.split(',').map(Number);
                setB(lat, lng);
            };

            // Auto-detect GPS for starting point if destination venue was prefilled from venue/event details
            if (prefill.to_lat && prefill.to_lng) {
                setB(parseFloat(prefill.to_lat), parseFloat(prefill.to_lng));
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        p => {
                            setA(p.coords.latitude, p.coords.longitude);
                            hint.textContent = @json(__('Location locked! Computing safest routes…'));
                            document.getElementById('go').click();
                        },
                        () => {
                            // Fallback to Dhaka central coordinate so safe routes are directly computed & displayed!
                            setA(23.7806, 90.4075);
                            hint.textContent = @json(__('Auto-selected Dhaka starting point. Click map to customize.'));
                            document.getElementById('go').click();
                        },
                        { enableHighAccuracy: true, timeout: 3500 }
                    );
                } else {
                    setA(23.7806, 90.4075);
                    document.getElementById('go').click();
                }
            }

            function clear() { layers.forEach(l => map.removeLayer(l)); layers = []; routeLayers = {}; }

            // Grade badge styling
            const gradeCls = g => ({
                A: 'bg-green-100 dark:bg-green-950/60 text-green-800 dark:text-green-300 border-green-300 dark:border-green-800',
                B: 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
                C: 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800',
                D: 'bg-orange-100 dark:bg-orange-950/60 text-orange-800 dark:text-orange-300 border-orange-300 dark:border-orange-800',
                E: 'bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300 border-red-300 dark:border-red-800',
                F: 'bg-red-200 dark:bg-red-900/60 text-red-900 dark:text-red-200 border-red-400 dark:border-red-700',
                U: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700',
            }[g] || 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-700');

            // Route highlight function
            function highlightRoute(key) {
                Object.entries(routeLayers).forEach(([k, ll]) => {
                    ll.forEach(l => { l.setStyle({ opacity: k === key ? 0.95 : 0.25, weight: k === key ? 7 : 3 }); if (k === key) l.bringToFront(); });
                });
                document.querySelectorAll('.pareto-card').forEach(c => {
                    c.classList.toggle('ring-2', c.dataset.key === key);
                    c.classList.toggle('ring-indigo-500', c.dataset.key === key);
                    c.classList.toggle('shadow-lg', c.dataset.key === key);
                });
            }

            document.getElementById('go').onclick = async () => {
                if (!A || !B) { hint.textContent = @json(__('Please set both A and B first.')); return; }
                const btn = document.getElementById('go');
                const spinnerHTML = '<svg class="inline w-4 h-4 mr-2 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>' + @json(__('Scoring routes…'));
                btn.disabled = true; btn.classList.add('opacity-60'); btn.innerHTML = spinnerHTML;
                const res = document.getElementById('result');
                const r = await NL.post('{{ route('route.compute') }}', { from_lat: A[0], from_lng: A[1], to_lat: B[0], to_lng: B[1], bucket: document.getElementById('bucket').value, mode: travelMode });
                btn.disabled = false; btn.classList.remove('opacity-60'); btn.textContent = @json(__('Find safest route'));
                res.classList.remove('hidden');
                if (!r.ok) { res.innerHTML = `<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4"><p class="text-red-600 dark:text-red-400">${NL.esc((r.data && (r.data.error || r.data.message)) || 'Error')}</p></div>`; return; }
                const d = r.data; routeData = d; clear();

                // Draw all routes faded
                d.routes.forEach((rt, i) => {
                    if (i === d.fastest || i === d.safest || i === d.well_lit) return;
                    layers.push(L.polyline(rt.coords, { color: '#94a3b8', weight: 2, opacity: .3, dashArray: '6 8' }).addTo(map));
                });

                // Draw the 3 key routes with different colors
                const routeColors = { fastest: '#64748b', safest: '#16a34a', well_lit: '#7c3aed' };
                const routeLabels = { fastest: @json(__('Fastest')), safest: @json(__('Safest')), well_lit: @json(__('Well-Lit')) };

                ['fastest', 'safest', 'well_lit'].forEach(key => {
                    const idx = d[key];
                    if (idx === undefined || idx === null) return;
                    const rt = d.routes[idx];
                    if (!rt) return;
                    const color = routeColors[key];
                    const pl = L.polyline(rt.coords, { color, weight: key === 'safest' ? 7 : 5, opacity: key === 'safest' ? 0.95 : 0.7 }).addTo(map).bindPopup(routeLabels[key]);
                    if (!routeLayers[key]) routeLayers[key] = [];
                    routeLayers[key].push(pl);
                    layers.push(pl);
                });

                // Draw safe havens along safest route
                const safe = d.routes[d.safest];
                (safe.haven_list || []).forEach(h => layers.push(
                    L.circleMarker([h.lat, h.lng], { radius: 7, color: '#2563eb', fillColor: '#60a5fa', fillOpacity: .9 }).addTo(map).bindPopup(NL.esc(h.type + ': ' + h.name))
                ));

                // Fit map to safest route
                const safeLine = routeLayers.safest?.[0];
                if (safeLine) map.fitBounds(safeLine.getBounds(), { padding: [30, 30] });

                // Build Pareto comparison cards
                const t = d.tradeoff;
                const buildCard = (key, obj, color, emoji) => {
                    if (!obj) return '';
                    return `<div class="pareto-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm border-l-4 p-4 cursor-pointer transition-all hover:shadow-md"
                                data-key="${key}"
                                style="border-left-color:${color}"
                                onclick="highlightRoute('${key}')">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">${emoji}</span>
                                <span class="font-bold text-sm" style="color:${color}">${NL.esc(obj.label)}</span>
                            </div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full border ${gradeCls(obj.grade)}">
                                ${obj.grade === 'U' ? @json(__('Unrated')) : ('@lang('Grade') ' + obj.grade)}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-700 dark:text-slate-300">
                            <div>🛣️ ${obj.distance_km} km</div>
                            <div>⏱️ ${obj.duration_min} min</div>
                            <div>⚠️ ${obj.incidents} @lang('incidents')</div>
                            <div>🌑 ${obj.dark_spots} @lang('dark spots')</div>
                            <div>🏥 ${obj.havens} @lang('help pts')</div>
                            <div>📊 ${obj.risk_per_km} @lang('risk/km')</div>
                        </div>
                        ${obj.extra_min > 0 ? `<div class="mt-2 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/80 rounded-lg px-2 py-1">+${obj.extra_min} min · +${obj.extra_km} km @lang('vs fastest')</div>` : ''}
                        ${obj.recommendation_reason ? `<div class="mt-2 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/80 rounded-lg p-2 border border-slate-100 dark:border-slate-700 flex items-start gap-1.5"><span class="text-xs">💡</span><span>${NL.esc(obj.recommendation_reason)}</span></div>` : ''}
                    </div>`;
                };

                let cardsHTML = `<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 space-y-2">
                    <p class="font-semibold text-green-700 dark:text-green-400 text-sm mb-1">${NL.esc(d.summary)}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">@lang('Click a card to highlight that route on the map.')</p>`;

                if (d.confidence) {
                    const isUnmonitored = d.confidence.level === 'unmonitored';
                    const isLimited = d.confidence.level === 'limited';
                    cardsHTML += `<div class="text-xs rounded-lg p-2.5 flex items-start gap-2 ${isUnmonitored ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800' : 'bg-slate-50 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700'}">
                        <span class="text-sm">${isUnmonitored ? '⚠️' : '📊'}</span>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">${isUnmonitored ? @json(__('Unmonitored Corridor · Lack of reports is not proof of safety')) : (isLimited ? @json(__('Limited Evidence on Record')) : @json(__('Sufficient Community Evidence')))}</div>
                            <div class="text-[11px] mt-0.5 text-slate-600 dark:text-slate-400">${NL.esc(d.confidence.disclaimer)}</div>
                            ${d.confidence.unverified_alerts > 0 ? `<div class="text-[11px] font-semibold text-amber-700 dark:text-amber-400 mt-1">⚠️ ${d.confidence.unverified_alerts} @lang('unverified alert(s) under review in this area.')</div>` : ''}
                        </div>
                    </div>`;
                }

                cardsHTML += `</div>`;

                cardsHTML += buildCard('fastest', t.fastest, '#64748b', '⚡');
                cardsHTML += buildCard('safest', t.safest, '#16a34a', '🛡️');
                if (t.well_lit) cardsHTML += buildCard('well_lit', t.well_lit, '#7c3aed', '💡');

                // Cost of Safety summary
                if (!t.same_route) {
                    const safest = t.safest;
                    cardsHTML += `<div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-950/40 dark:to-emerald-950/30 rounded-xl shadow-sm border border-green-200 dark:border-green-800 p-4">
                        <div class="font-bold text-sm text-green-800 dark:text-green-300 mb-1">🎯 @lang('Cost of Safety')</div>
                        <p class="text-xs text-green-700 dark:text-green-300/90">
                            @lang('Trading') <strong>${safest.extra_min} @lang('minute(s)')</strong> @lang('of detour buys you a')
                            <strong>@lang('Grade') ${NL.esc(safest.grade)}</strong> @lang('route that avoids')
                            <strong>${Math.max(0, t.fastest.incidents - safest.incidents)}</strong> @lang('incident area(s)')
                            @lang('and passes') <strong>${safest.havens}</strong> @lang('help point(s).')
                        </p>
                    </div>`;
                }

                cardsHTML += `<div class="text-xs text-slate-500 dark:text-slate-400 px-1">@lang('Routing by') ${NL.esc(d.source)}. @lang('Routes more than 30% longer than the fastest are never recommended.')</div>`;
                res.innerHTML = cardsHTML;

                // Highlight safest route by default
                highlightRoute('safest');
            };
        </script>
    @endpush
</x-app-layout>
