<x-app-layout>
    @include('partials.leaflet')
    <div class="max-w-xl mx-auto p-4 space-y-3">
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">📍 {{ $trip->kind === 'ride' ? __('Ride check-in') : __('Live trip') }}</h1>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 text-sm space-y-2 text-slate-700 dark:text-slate-300">
            @if ($trip->destination)<div>{{ __('To') }}: <b class="text-slate-900 dark:text-slate-100">{{ $trip->destination }}</b></div>@endif
            @if ($trip->vehicle_plate)<div>{{ __('Vehicle') }}: <b class="text-slate-900 dark:text-slate-100">{{ $trip->vehicle_plate }}</b></div>@endif
            <div>{{ __('Link expires') }}: <b class="text-slate-900 dark:text-slate-100">{{ $trip->expires_at->format('d M, h:i A') }}</b></div>
            <div class="flex gap-2">
                <input id="link" readonly value="{{ $link }}" class="flex-1 border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-md text-xs">
                <button type="button" id="copy" class="border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-md px-3 text-xs transition">{{ __('Copy') }}</button>
            </div>
            <div id="state" class="text-slate-600 dark:text-slate-400"></div>
            <p class="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 p-2.5 rounded-lg">{{ __('Keep this tab open. Browsers stop sending location when the tab is closed or the screen is off for a long time.') }}</p>
        </div>
        <div id="map" style="height:300px" class="rounded-xl border border-slate-300 dark:border-slate-700 z-0"></div>
        <form method="POST" action="{{ route('trips.end', $trip->token) }}">@csrf
            <button class="w-full bg-green-600 hover:bg-green-500 text-white rounded-md py-2.5 font-semibold transition">✔ {{ __('I arrived safely - end trip') }}</button>
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
