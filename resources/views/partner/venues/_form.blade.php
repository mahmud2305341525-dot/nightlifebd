@php $v = $venue ?? null; @endphp
<div class="space-y-3">
    <input name="name" placeholder="{{ __('Venue name') }}" class="border-slate-300 rounded-md w-full text-sm" value="{{ old('name', $v?->name) }}">
    <div class="grid grid-cols-2 gap-3">
        <select name="category" class="border-slate-300 rounded-md text-sm">
            @foreach (\App\Models\Venue::CATEGORIES as $c)<option value="{{ $c }}" @selected(old('category', $v?->category) === $c)>{{ ucfirst($c) }}</option>@endforeach
        </select>
        <select name="price_tier" class="border-slate-300 rounded-md text-sm">
            @foreach ([1 => 'Budget', 2 => 'Mid', 3 => 'High'] as $k => $l)<option value="{{ $k }}" @selected(old('price_tier', $v?->price_tier ?? 2) == $k)>{{ __($l) }}</option>@endforeach
        </select>
    </div>
    <input name="area" placeholder="{{ __('Area (e.g. Gulshan)') }}" class="border-slate-300 rounded-md w-full text-sm" value="{{ old('area', $v?->area) }}">
    <div class="grid grid-cols-3 gap-3 items-end">
        <label class="text-xs">{{ __('Opens') }}<input type="time" name="opens_at" class="border-slate-300 rounded-md w-full text-sm" value="{{ old('opens_at', $v?->opens_at ? substr($v->opens_at, 0, 5) : '') }}"></label>
        <label class="text-xs">{{ __('Closes') }}<input type="time" name="closes_at" class="border-slate-300 rounded-md w-full text-sm" value="{{ old('closes_at', $v?->closes_at ? substr($v->closes_at, 0, 5) : '') }}"></label>
        <label class="text-xs">{{ __('Lighting (1-5)') }}<input type="number" min="1" max="5" name="lighting" class="border-slate-300 rounded-md w-full text-sm" value="{{ old('lighting', $v?->lighting ?? 3) }}"></label>
    </div>
    <div class="flex gap-5 text-sm">
        <label><input type="checkbox" name="has_cctv" value="1" class="rounded" @checked(old('has_cctv', $v?->has_cctv))> CCTV</label>
        <label><input type="checkbox" name="has_guard" value="1" class="rounded" @checked(old('has_guard', $v?->has_guard))> {{ __('Security guard') }}</label>
    </div>
    <input name="vibe_tags" placeholder="{{ __('Vibe tags, comma separated (quiet, family, music, view)') }}" class="border-slate-300 rounded-md w-full text-sm" value="{{ old('vibe_tags', $v?->vibe_tags) }}">
    <textarea name="description" rows="3" class="border-slate-300 rounded-md w-full text-sm" placeholder="{{ __('Description') }}">{{ old('description', $v?->description) }}</textarea>
    <p class="text-sm">{{ __('Click the map to set the venue location:') }}</p>
    <div id="map" style="height:300px" class="rounded-lg border border-slate-300 z-0"></div>
    <input type="hidden" name="lat" id="lat" value="{{ old('lat', $v?->lat) }}">
    <input type="hidden" name="lng" id="lng" value="{{ old('lng', $v?->lng) }}">
    @if ($errors->any())<ul class="text-red-600 text-sm list-disc ms-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
</div>
@push('scripts')
    <script>
        const map = NL.map('map', [{{ $v?->lat ?? 23.7806 }}, {{ $v?->lng ?? 90.4075 }}], {{ $v ? 16 : 12 }});
        let marker = null;
        const latI = document.getElementById('lat'), lngI = document.getElementById('lng');
        function setPoint(a, b) { if (marker) map.removeLayer(marker); marker = L.marker([a, b]).addTo(map); latI.value = a; lngI.value = b; }
        if (latI.value && lngI.value) setPoint(parseFloat(latI.value), parseFloat(lngI.value));
        map.on('click', e => setPoint(e.latlng.lat, e.latlng.lng));
    </script>
@endpush
