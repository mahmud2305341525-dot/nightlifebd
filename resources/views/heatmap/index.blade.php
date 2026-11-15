<x-app-layout>
    @include('partials.leaflet')
    @push('scripts')
        <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    @endpush
    <x-slot name="header">
        <h2 class="font-semibold text-xl">{{ __('Night safety heatmap') }}</h2>
        <p class="text-sm text-slate-500">{{ __('Verified incident reports and community dark spots, weighted by severity and reporter trust. Drag the slider to change the hour.') }}</p>
    </x-slot>
    <div class="max-w-6xl mx-auto p-4 space-y-3">
        <div class="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4 flex-wrap text-sm">
            <label class="font-semibold">{{ __('Hour') }}: <span id="hr-txt" class="text-indigo-700"></span></label>
            <input id="hr" type="range" min="0" max="23" value="{{ \App\Support\TimeBucket::hourFor($bucket) }}" class="flex-1 min-w-[12rem]">
            <span id="bucket-txt" class="text-slate-600"></span>
        </div>
        <div id="map" style="height:540px" class="rounded-xl border border-slate-300 z-0"></div>
    </div>
    @push('scripts')
        <script>
            const map = NL.map('map', NL.DHAKA, 12);
            let heat = null;
            const hr = document.getElementById('hr');
            const bucketOf = h => (h >= 6 && h < 18) ? 'day' : (h >= 18 && h < 22) ? 'evening' : (h >= 22 || h < 2) ? 'night' : 'late';
            const names = @json(\App\Support\TimeBucket::labels());
            async function load() {
                const h = parseInt(hr.value, 10), b = bucketOf(h);
                document.getElementById('hr-txt').textContent = String(h).padStart(2, '0') + ':00';
                document.getElementById('bucket-txt').textContent = names[b];
                const r = await fetch('{{ route('api.heat') }}?bucket=' + b);
                const d = await r.json();
                if (heat) map.removeLayer(heat);
                heat = L.heatLayer(d.points, { radius: 28, blur: 22, maxZoom: 15, gradient: { 0.3: '#facc15', 0.6: '#f97316', 1: '#dc2626' } }).addTo(map);
            }
            hr.addEventListener('change', load); hr.addEventListener('input', () => { document.getElementById('hr-txt').textContent = String(hr.value).padStart(2, '0') + ':00'; });
            window.addEventListener('load', load);
        </script>
    @endpush
</x-app-layout>
