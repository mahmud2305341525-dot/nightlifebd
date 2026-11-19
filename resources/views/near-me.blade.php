<x-app-layout>
    @include('partials.leaflet')
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ __('24/7 Safe Havens — Nearest Police, Hospitals & Pharmacies') }}</h2>
        <p class="text-sm text-slate-500">{{ __('All help points are open 24/7. In a life-threatening emergency, call 999 first. ETA estimated at Dhaka traffic speed (~20 km/h).') }}</p>
    </x-slot>
    <div class="max-w-6xl mx-auto p-4 grid md:grid-cols-2 gap-4">
        <div class="space-y-3">
            {{-- Emergency call --}}
            <a href="tel:999" class="flex items-center justify-center gap-2 rounded-xl bg-red-600 hover:bg-red-500 text-white py-3.5 font-bold text-lg shadow-md transition">
                📞 {{ __('Call 999 (Police / Ambulance / Fire)') }}
            </a>

            {{-- Type filters --}}
            <div class="flex gap-2 text-sm flex-wrap">
                <button data-t="" class="flt px-3 py-1.5 rounded-full border bg-slate-900 text-white font-semibold">{{ __('All') }}</button>
                <button data-t="police" class="flt px-3 py-1.5 rounded-full border bg-white font-semibold">🚓 {{ __('Police') }}</button>
                <button data-t="hospital" class="flt px-3 py-1.5 rounded-full border bg-white font-semibold">🏥 {{ __('Hospital') }}</button>
                <button data-t="pharmacy" class="flt px-3 py-1.5 rounded-full border bg-white font-semibold">💊 {{ __('24/7 Pharmacy') }}</button>
            </div>

            <p id="msg" class="text-sm text-slate-500">{{ __('Finding your location…') }}</p>

            {{-- Stats bar --}}
            <div id="stats" class="hidden grid grid-cols-3 gap-2 text-center text-xs">
                <div class="bg-blue-50 border border-blue-200 rounded-lg py-2">
                    <div id="ct-police" class="text-lg font-bold text-blue-700">—</div>
                    <div class="text-blue-600">{{ __('Police') }}</div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg py-2">
                    <div id="ct-hospital" class="text-lg font-bold text-red-700">—</div>
                    <div class="text-red-600">{{ __('Hospitals') }}</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg py-2">
                    <div id="ct-pharmacy" class="text-lg font-bold text-green-700">—</div>
                    <div class="text-green-600">{{ __('Pharmacies') }}</div>
                </div>
            </div>

            <ul id="list" class="bg-white rounded-xl shadow-sm divide-y text-sm"></ul>
        </div>
        <div id="map" style="height:560px" class="rounded-xl border border-slate-300 z-0"></div>
    </div>
    @push('scripts')
        <script>
            const map = NL.map('map', NL.DHAKA, 12);
            let me = null, type = '', layer = L.layerGroup().addTo(map), allItems = [];

            // Colour-coded circle icons per type
            const typeColor = { police: '#2563eb', hospital: '#dc2626', pharmacy: '#16a34a' };
            const typeEmoji = { police: '🚓', hospital: '🏥', pharmacy: '💊' };

            function makeIcon(t) {
                const c = typeColor[t] || '#64748b';
                return L.divIcon({
                    className: '',
                    html: `<div style="background:${c};width:22px;height:22px;border-radius:50%;border:3px solid white;box-shadow:0 2px 6px #0004"></div>`,
                    iconSize: [22, 22], iconAnchor: [11, 11]
                });
            }

            async function load() {
                if (!me) return;
                const r = await fetch(`{{ route('api.nearest') }}?lat=${me.lat}&lng=${me.lng}${type ? '&type=' + type : ''}`);
                allItems = await r.json();
                renderList(allItems);
            }

            function renderList(items) {
                layer.clearLayers();
                const list = document.getElementById('list'); list.innerHTML = '';
                const stats = document.getElementById('stats');

                if (!items.length) {
                    list.innerHTML = '<li class="p-3 text-slate-500">{{ __("No help points found. Run: php artisan db:seed --class=SafeHavenSeeder") }}</li>';
                    return;
                }

                // Update type counts
                const counts = { police: 0, hospital: 0, pharmacy: 0 };
                items.forEach(h => { if (counts[h.type] !== undefined) counts[h.type]++; });
                document.getElementById('ct-police').textContent = counts.police;
                document.getElementById('ct-hospital').textContent = counts.hospital;
                document.getElementById('ct-pharmacy').textContent = counts.pharmacy;
                stats.classList.remove('hidden');

                // Render markers + list
                items.slice(0, 10).forEach(h => {
                    L.marker([h.lat, h.lng], { icon: makeIcon(h.type) }).addTo(layer)
                        .bindPopup(`<strong>${NL.esc(h.name)}</strong><br>${NL.esc(h.type)}${h.phone ? ' · ' + NL.esc(h.phone) : ''}<br>${(h.distance_m/1000).toFixed(1)} km · ~${h.eta_min} min`);

                    const li = document.createElement('li');
                    li.className = 'p-3 flex justify-between gap-2 hover:bg-slate-50 transition';
                    const color = typeColor[h.type] || '#64748b';
                    li.innerHTML = `
                        <div class="flex gap-3 items-start min-w-0">
                            <span class="text-xl flex-shrink-0">${typeEmoji[h.type] || '📍'}</span>
                            <div class="min-w-0">
                                <div class="font-semibold truncate" style="color:${color}">${NL.esc(h.name)}</div>
                                <div class="text-xs text-slate-500">${NL.esc(h.type)}${h.phone ? ' · <a href="tel:' + NL.esc(h.phone) + '" class="text-indigo-600 underline">' + NL.esc(h.phone) + '</a>' : ''}</div>
                            </div>
                        </div>
                        <div class="text-right text-xs flex-shrink-0 space-y-1">
                            <div class="font-bold">${(h.distance_m/1000).toFixed(1)} km</div>
                            <div class="text-slate-500">~${h.eta_min} min</div>
                            <a class="inline-block text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-semibold hover:bg-indigo-100 transition" target="_blank" rel="noopener"
                                href="https://www.google.com/maps/dir/${me.lat},${me.lng}/${h.lat},${h.lng}">{{ __('Directions') }}</a>
                        </div>`;
                    list.appendChild(li);
                });
            }

            document.querySelectorAll('.flt').forEach(b => b.onclick = () => {
                document.querySelectorAll('.flt').forEach(x => { x.classList.remove('bg-slate-900', 'text-white'); x.classList.add('bg-white'); });
                b.classList.add('bg-slate-900', 'text-white'); b.classList.remove('bg-white');
                type = b.dataset.t; load();
            });

            NL.locate().then(p => {
                me = p;
                document.getElementById('msg').textContent = @json(__('Showing the 10 nearest 24/7 safe havens to your location.'));
                map.setView([p.lat, p.lng], 14);
                L.circleMarker([p.lat, p.lng], { radius: 10, color: '#7c3aed', fillColor: '#a78bfa', fillOpacity: .9, weight: 3 }).addTo(map).bindPopup(@json(__('📍 You are here')));
                load();
            }).catch(() => {
                me = { lat: NL.DHAKA[0], lng: NL.DHAKA[1] };
                document.getElementById('msg').textContent = @json(__('Location unavailable — showing help points near central Dhaka. Enable location for nearest results.'));
                load();
            });
        </script>
    @endpush
</x-app-layout>
