<x-app-layout>
    @include('partials.leaflet')
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ __('Nearest police, hospitals and pharmacies') }}</h2>
        <p class="text-sm text-slate-500">{{ __('In an emergency call 999. ETA is an estimate at 20 km/h Dhaka traffic speed.') }}</p>
    </x-slot>
    <div class="max-w-5xl mx-auto p-4 grid md:grid-cols-2 gap-4">
        <div class="space-y-3">
            <div class="flex gap-2 text-sm flex-wrap">
                <button data-t="" class="flt px-3 py-1.5 rounded-full border bg-slate-900 text-white">{{ __('All') }}</button>
                <button data-t="police" class="flt px-3 py-1.5 rounded-full border bg-white">🚓 {{ __('Police') }}</button>
                <button data-t="hospital" class="flt px-3 py-1.5 rounded-full border bg-white">🏥 {{ __('Hospital') }}</button>
                <button data-t="pharmacy" class="flt px-3 py-1.5 rounded-full border bg-white">💊 {{ __('24/7 pharmacy') }}</button>
            </div>
            <a href="tel:999" class="block text-center rounded-lg bg-red-600 text-white py-3 font-bold">📞 {{ __('Call 999') }}</a>
            <p id="msg" class="text-sm text-slate-500">{{ __('Finding your location...') }}</p>
            <ul id="list" class="bg-white rounded-xl shadow-sm divide-y text-sm"></ul>
        </div>
        <div id="map" style="height:480px" class="rounded-xl border border-slate-300 z-0"></div>
    </div>
    @push('scripts')
        <script>
            const map = NL.map('map', NL.DHAKA, 12);
            let me = null, type = '', layer = L.layerGroup().addTo(map), meMarker = null;
            const icons = { police: '🚓', hospital: '🏥', pharmacy: '💊' };
            async function load() {
                if (!me) return;
                const r = await fetch(`{{ route('api.nearest') }}?lat=${me.lat}&lng=${me.lng}${type ? '&type=' + type : ''}`);
                const items = await r.json();
                layer.clearLayers();
                const list = document.getElementById('list'); list.innerHTML = '';
                if (!items.length) list.innerHTML = '<li class="p-3 text-slate-500">{{ __('No help points saved yet. Run: php artisan havens:import') }}</li>';
                items.forEach(h => {
                    L.marker([h.lat, h.lng]).addTo(layer).bindPopup(NL.esc(h.name));
                    const li = document.createElement('li'); li.className = 'p-3 flex justify-between gap-2';
                    li.innerHTML = `<span>${icons[h.type] || ''} <b>${NL.esc(h.name)}</b><br><span class="text-xs text-slate-500">${NL.esc(h.type)}${h.phone ? ' · ' + NL.esc(h.phone) : ''}</span></span>
                        <span class="text-right text-xs">${(h.distance_m / 1000).toFixed(1)} km<br>~${h.eta_min} min<br><a class="text-indigo-600 underline" target="_blank" rel="noopener" href="https://www.openstreetmap.org/directions?from=${me.lat},${me.lng}&to=${h.lat},${h.lng}">{{ __('Directions') }}</a></span>`;
                    list.appendChild(li);
                });
            }
            document.querySelectorAll('.flt').forEach(b => b.onclick = () => {
                document.querySelectorAll('.flt').forEach(x => { x.classList.remove('bg-slate-900', 'text-white'); x.classList.add('bg-white'); });
                b.classList.add('bg-slate-900', 'text-white'); b.classList.remove('bg-white'); type = b.dataset.t; load();
            });
            NL.locate().then(p => {
                me = p; document.getElementById('msg').textContent = @json(__('Showing the 10 nearest to you.'));
                map.setView([p.lat, p.lng], 14); L.circleMarker([p.lat, p.lng], { radius: 9, color: '#2563eb', fillOpacity: .9 }).addTo(map).bindPopup(@json(__('You are here'))); load();
            }).catch(() => {
                me = { lat: NL.DHAKA[0], lng: NL.DHAKA[1] };
                document.getElementById('msg').textContent = @json(__('Location unavailable (needs HTTPS or localhost and permission). Showing help points near central Dhaka.')); load();
            });
        </script>
    @endpush
</x-app-layout>
