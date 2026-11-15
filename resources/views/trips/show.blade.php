<x-app-layout>
    @include('partials.leaflet')
    <div class="max-w-xl mx-auto p-4 space-y-3">
        <h1 class="text-xl font-bold">📍 {{ $trip->kind === 'ride' ? __('Ride check-in') : __('Live trip') }}</h1>
        <div class="bg-white rounded-xl shadow-sm p-4 text-sm space-y-2">
            @if ($trip->destination)<div>{{ __('To') }}: <b>{{ $trip->destination }}</b></div>@endif
            @if ($trip->vehicle_plate)<div>{{ __('Vehicle') }}: <b>{{ $trip->vehicle_plate }}</b></div>@endif
            <div>{{ __('Link expires') }}: <b>{{ $trip->expires_at->format('d M, h:i A') }}</b></div>
            <div class="flex gap-2">
                <input id="link" readonly value="{{ $link }}" class="flex-1 border-slate-300 rounded-md text-xs">
                <button type="button" id="copy" class="border rounded-md px-3 text-xs">{{ __('Copy') }}</button>
            </div>
            <div id="state" class="text-slate-600"></div>
            <p class="text-xs text-amber-700">{{ __('Keep this tab open. Browsers stop sending location when the tab is closed or the screen is off for a long time.') }}</p>
        </div>
        <div id="map" style="height:300px" class="rounded-xl border border-slate-300 z-0"></div>
        <form method="POST" action="{{ route('trips.end', $trip->token) }}">@csrf
            <button class="w-full bg-green-600 text-white rounded-md py-2.5 font-semibold">✔ {{ __('I arrived safely - end trip') }}</button>
        </form>
    </div>
    @push('scripts')
        <script>
            const map = NL.map('map', NL.DHAKA, 14);
            let me = null;
            const state = document.getElementById('state');
            document.getElementById('copy').onclick = () => { navigator.clipboard && navigator.clipboard.writeText(document.getElementById('link').value); };
            async function send(p) {
                const r = await NL.post('{{ route('trips.ping', $trip->token) }}', { lat: p.lat, lng: p.lng });
                state.textContent = r.ok ? '{{ __('Location shared') }}: ' + new Date().toLocaleTimeString() : (r.status === 410 ? @json(__('Trip expired')) : @json(__('Could not send location')));
                if (r.status === 410) clearInterval(iv);
                if (!me) me = L.circleMarker([p.lat, p.lng], { radius: 9, color: '#2563eb', fillOpacity: .9 }).addTo(map); else me.setLatLng([p.lat, p.lng]);
                map.setView([p.lat, p.lng], 16);
            }
            async function tick() { try { await send(await NL.locate()); } catch (e) { state.textContent = @json(__('Location permission needed (HTTPS or localhost).')); } }
            tick(); const iv = setInterval(tick, 10000);
        </script>
    @endpush
</x-app-layout>
