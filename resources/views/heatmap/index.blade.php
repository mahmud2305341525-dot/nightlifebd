<x-app-layout>
    @include('partials.leaflet')
    @push('head')
        <style>
            #hr::-webkit-slider-thumb { background: #4f46e5; }
            #hr::-webkit-slider-runnable-track { background: linear-gradient(to right, #22c55e, #f59e0b, #ef4444); border-radius: 4px; }
            .time-pill { transition: all .3s; }
            @keyframes heatPulse { 0%,100%{opacity:1} 50%{opacity:.7} }
            .heat-loading { animation: heatPulse .8s infinite; }
        </style>
    @endpush

    <x-slot name="header">
        <h2 class="font-semibold text-xl">🔥 {{ __('Night Safety Heatmap') }}</h2>
        <p class="text-sm text-slate-500">{{ __('Verified incident reports + community dark spots, weighted by severity and reporter trust. Drag the slider or press Play to animate through the day.') }}</p>
    </x-slot>

    <div class="max-w-6xl mx-auto p-4 space-y-3">

        {{-- Control panel --}}
        <div class="bg-white rounded-xl shadow-sm p-4 space-y-3">

            {{-- Time bucket pills --}}
            <div class="flex gap-2 flex-wrap text-xs font-semibold">
                @foreach (['day' => ['🌤️', '6 AM – 6 PM', 'bg-amber-100 text-amber-800 border-amber-300'],
                           'evening' => ['🌆', '6 PM – 10 PM', 'bg-orange-100 text-orange-800 border-orange-300'],
                           'night' => ['🌙', '10 PM – 2 AM', 'bg-indigo-100 text-indigo-800 border-indigo-300'],
                           'late' => ['🌃', '2 AM – 6 AM', 'bg-slate-100 text-slate-700 border-slate-300']] as $b => [$icon, $label, $cls])
                    <span id="pill-{{ $b }}" class="time-pill border rounded-full px-3 py-1 {{ $cls }} opacity-40">{{ $icon }} {{ $label }}</span>
                @endforeach
                <span id="risk-badge" class="ml-auto border rounded-full px-3 py-1 text-xs font-bold bg-green-100 text-green-800 border-green-300">● {{ __('Loading…') }}</span>
            </div>

            {{-- Slider row --}}
            <div class="flex items-center gap-3">
                {{-- Play/Pause --}}
                <button id="btn-play" type="button"
                    class="flex-shrink-0 w-10 h-10 rounded-full bg-indigo-600 hover:bg-indigo-500 text-white flex items-center justify-center shadow transition-all"
                    title="{{ __('Auto-play through all hours') }}">
                    <svg id="icon-play" class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                    <svg id="icon-pause" class="w-5 h-5 hidden" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.75 3a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75A.75.75 0 007.25 3h-1.5zm5.5 0a.75.75 0 00-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 00.75-.75V3.75a.75.75 0 00-.75-.75h-1.5z" clip-rule="evenodd"/></svg>
                </button>

                <div class="flex-1 space-y-1">
                    <div class="flex justify-between text-xs text-slate-400 px-1">
                        <span>12 AM</span><span>6 AM</span><span>12 PM</span><span>6 PM</span><span>11 PM</span>
                    </div>
                    <input id="hr" type="range" min="0" max="23" value="{{ \App\Support\TimeBucket::hourFor($bucket) }}"
                        class="w-full h-2 rounded-lg appearance-none cursor-pointer accent-indigo-600">
                </div>

                <div class="flex-shrink-0 text-right">
                    <div id="hr-txt" class="text-2xl font-extrabold tabular-nums text-slate-900">00:00</div>
                    <div id="bucket-txt" class="text-xs text-slate-500"></div>
                </div>
            </div>

            {{-- Layer toggles --}}
            <div class="flex gap-3 text-xs">
                <label class="flex items-center gap-1.5 cursor-pointer select-none">
                    <input type="checkbox" id="toggle-incidents" checked class="accent-red-500 w-3.5 h-3.5">
                    <span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span> {{ __('Incidents') }}
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer select-none">
                    <input type="checkbox" id="toggle-darkspots" checked class="accent-amber-500 w-3.5 h-3.5">
                    <span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span> {{ __('Dark spots') }}
                </label>
                <span id="point-count" class="ml-auto text-slate-400"></span>
            </div>
        </div>

        {{-- Map --}}
        <div class="relative">
            <div id="map" style="height:560px" class="rounded-xl border border-slate-300 z-0"></div>

            {{-- Legend overlay --}}
            <div class="absolute bottom-4 left-4 z-[500] bg-white/90 backdrop-blur-sm rounded-xl shadow-md p-3 text-xs space-y-1.5">
                <div class="font-bold text-slate-700 mb-2">{{ __('Risk level') }}</div>
                <div class="flex items-center gap-2"><span class="w-4 h-2 rounded inline-block bg-yellow-400"></span> {{ __('Low') }}</div>
                <div class="flex items-center gap-2"><span class="w-4 h-2 rounded inline-block bg-orange-500"></span> {{ __('Medium') }}</div>
                <div class="flex items-center gap-2"><span class="w-4 h-2 rounded inline-block bg-red-600"></span> {{ __('High') }}</div>
                <div class="flex items-center gap-2 pt-1 border-t"><span class="w-3 h-3 rounded-full inline-block bg-amber-400 border border-amber-600"></span> {{ __('Dark spot') }}</div>
            </div>

            {{-- Loading overlay --}}
            <div id="loading" class="hidden absolute inset-0 bg-white/40 backdrop-blur-[2px] rounded-xl flex items-center justify-center z-[400]">
                <div class="bg-white rounded-xl px-5 py-3 shadow-lg flex items-center gap-3">
                    <svg class="w-5 h-5 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                    <span class="text-sm font-semibold text-slate-700">{{ __('Updating heatmap…') }}</span>
                </div>
            </div>
        </div>

        {{-- Info strip --}}
        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 rounded-xl p-4 text-sm text-indigo-800">
            <span class="font-bold">📊 {{ __('How risk is calculated:') }}</span>
            {{ __('Each point = severity/5 × reporter trust score × time-match factor. Reports that happened around the selected hour are weighted significantly higher than off-hour reports.') }}
        </div>
    </div>

    @push('scripts')
    {{-- Load Leaflet.heat AFTER leaflet.js has loaded --}}
    <script src="/js/leaflet-heat.js"></script>
    <script>
        const map = NL.map('map', NL.DHAKA, 12);
        const names = @json(\App\Support\TimeBucket::labels());

        let heatAll = null;
        let incidents = [];
        let darkSpots = [];
        let playing = false, playTimer = null;
        let showIncidents = true, showDarkSpots = true;

        const hr        = document.getElementById('hr');
        const hrTxt     = document.getElementById('hr-txt');
        const bucketTxt = document.getElementById('bucket-txt');
        const loading   = document.getElementById('loading');
        const countEl   = document.getElementById('point-count');
        const riskBadge = document.getElementById('risk-badge');

        const bucketOf = h => (h >= 6 && h < 18) ? 'day' : (h >= 18 && h < 22) ? 'evening' : (h >= 22 || h < 2) ? 'night' : 'late';

        function updatePills(b) {
            ['day', 'evening', 'night', 'late'].forEach(k => {
                const el = document.getElementById('pill-' + k);
                if (el) {
                    el.classList.toggle('opacity-40', k !== b);
                    el.classList.toggle('scale-105', k === b);
                    el.classList.toggle('shadow-sm', k === b);
                }
            });
        }

        function riskSummary(points) {
            if (!points.length) return { label: @json(__('No data')), cls: 'bg-slate-100 text-slate-700 border-slate-300' };
            const avg = points.reduce((s, p) => s + (p[2] || 0), 0) / points.length;
            if (avg < 0.25) return { label: '🟢 ' + @json(__('Low Risk')), cls: 'bg-green-100 text-green-800 border-green-300' };
            if (avg < 0.55) return { label: '🟡 ' + @json(__('Moderate Risk')), cls: 'bg-amber-100 text-amber-800 border-amber-300' };
            return { label: '🔴 ' + @json(__('High Risk')), cls: 'bg-red-100 text-red-800 border-red-300' };
        }

        // Dynamically compute points and intensity for a given hour (0-23)
        function computePoints(h) {
            const pts = [];

            if (showIncidents && incidents.length) {
                incidents.forEach(item => {
                    // Circular hour difference between incident time and slider time (0 to 12)
                    const diff = Math.min(Math.abs(item.hour - h), 24 - Math.abs(item.hour - h));

                    // Time-decay factor: exact hour = 1.0, 1 hr away = 0.8, 2 hr = 0.55, 3 hr = 0.35, 4+ hr = 0.15
                    let mult = 0.15;
                    if (diff === 0) mult = 1.0;
                    else if (diff === 1) mult = 0.82;
                    else if (diff === 2) mult = 0.58;
                    else if (diff === 3) mult = 0.38;
                    else if (diff <= 5) mult = 0.24;

                    const w = Math.min(1.0, item.weight * mult * 1.6);
                    pts.push([item.lat, item.lng, Math.round(w * 100) / 100]);
                });
            }

            if (showDarkSpots && darkSpots.length) {
                // Dark spots present much higher risk during darkness (6 PM to 6 AM)
                const darkIntensity = (h >= 18 || h < 6) ? 0.6 : 0.25;
                darkSpots.forEach(d => {
                    pts.push([d.lat, d.lng, darkIntensity]);
                });
            }

            return pts;
        }

        function updateHeatmap(h) {
            const b = bucketOf(h);
            hrTxt.textContent = String(h).padStart(2, '0') + ':00';
            bucketTxt.textContent = names[b] || b;
            updatePills(b);

            const pts = computePoints(h);

            if (!heatAll) {
                if (typeof L.heatLayer === 'function') {
                    heatAll = L.heatLayer(pts, {
                        radius: 32,
                        blur: 24,
                        maxZoom: 16,
                        gradient: { 0.2: '#fde68a', 0.5: '#f97316', 0.8: '#dc2626', 1.0: '#7f1d1d' }
                    }).addTo(map);
                }
            } else {
                heatAll.setLatLngs(pts);
            }

            const rs = riskSummary(pts);
            riskBadge.textContent = rs.label;
            riskBadge.className = `ml-auto time-pill border rounded-full px-3 py-1 text-xs font-bold ${rs.cls}`;
            countEl.textContent = pts.length + ' ' + @json(__('data points'));
        }

        // Live slider updates on drag (input) and on release (change)
        hr.addEventListener('input', () => updateHeatmap(parseInt(hr.value, 10)));
        hr.addEventListener('change', () => updateHeatmap(parseInt(hr.value, 10)));

        // Layer toggles
        document.getElementById('toggle-incidents').addEventListener('change', e => {
            showIncidents = e.target.checked;
            updateHeatmap(parseInt(hr.value, 10));
        });
        document.getElementById('toggle-darkspots').addEventListener('change', e => {
            showDarkSpots = e.target.checked;
            updateHeatmap(parseInt(hr.value, 10));
        });

        // Play / Pause loop
        const btnPlay   = document.getElementById('btn-play');
        const iconPlay  = document.getElementById('icon-play');
        const iconPause = document.getElementById('icon-pause');

        btnPlay.addEventListener('click', () => {
            playing = !playing;
            iconPlay.classList.toggle('hidden', playing);
            iconPause.classList.toggle('hidden', !playing);
            btnPlay.classList.toggle('bg-red-500', playing);
            btnPlay.classList.toggle('bg-indigo-600', !playing);

            if (playing) {
                playTimer = setInterval(() => {
                    const next = (parseInt(hr.value, 10) + 1) % 24;
                    hr.value = next;
                    updateHeatmap(next);
                }, 800); // Advance 1 hour every 0.8s
            } else {
                clearInterval(playTimer);
            }
        });

        // Initial fetch: retrieve all incident and dark spot records once
        (async () => {
            loading.classList.remove('hidden');
            try {
                const res = await fetch('{{ route('api.heat') }}');
                const data = await res.json();
                incidents = data.incidents || [];
                darkSpots = data.dark_spots || [];

                // Fallback if data format is plain points
                if (!incidents.length && data.points) {
                    data.points.forEach(p => {
                        incidents.push({ lat: p[0], lng: p[1], hour: 22, weight: p[2] || 0.5 });
                    });
                }

                updateHeatmap(parseInt(hr.value, 10));
            } catch (err) {
                console.error('Heatmap load error:', err);
                riskBadge.textContent = '❌ Error loading data';
            } finally {
                loading.classList.add('hidden');
            }
        })();
    </script>
    @endpush
</x-app-layout>
