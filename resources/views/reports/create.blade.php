<x-app-layout>
    @include('partials.leaflet')
    <x-slot name="header"><h2 class="font-semibold text-xl">{{ __('Report an incident or write a review') }}</h2></x-slot>
    <div class="max-w-2xl mx-auto p-4">
        <form method="POST" action="{{ route('reports.store') }}" class="bg-white rounded-xl shadow-sm p-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-2">
                @foreach (['incident' => __('Incident / safety issue'), 'review' => __('Venue review')] as $k => $l)
                    <label class="border rounded-lg p-3 text-sm cursor-pointer has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="kind" value="{{ $k }}" class="me-2" @checked(old('kind', 'incident') === $k)>{{ $l }}
                    </label>
                @endforeach
            </div>

            <div>
                <label class="text-sm font-medium">{{ __('What happened? (Bangla, English or Banglish)') }}</label>
                <textarea name="body" rows="5" maxlength="500" id="body" class="mt-1 w-full border-slate-300 rounded-md text-sm" placeholder="রাত ১১টায় ধানমন্ডি ২৭ এর কাছে রাস্তায় আলো নেই, একজন ছিনতাই করার চেষ্টা করেছে...">{{ old('body') }}</textarea>
                <div class="text-xs text-slate-500 text-right"><span id="count">0</span>/500</div>
                @error('body')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="text-sm font-medium">{{ __('Venue (optional if you mark the place on the map)') }}</label>
                <select name="venue_id" id="venue_id" class="mt-1 w-full border-slate-300 rounded-md text-sm">
                    <option value="">{{ __('- none -') }}</option>
                    @foreach ($venues as $v)
                        <option value="{{ $v->id }}" data-lat="{{ $v->lat }}" data-lng="{{ $v->lng }}" @selected(old('venue_id', $selected) == $v->id)>{{ $v->name }} ({{ $v->area }})</option>
                    @endforeach
                </select>
                @error('venue_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="text-sm font-medium">{{ __('Mark the place on the map (click)') }}</label>
                <div id="map" style="height:260px" class="mt-1 rounded-lg border border-slate-300 z-0"></div>
                <input type="hidden" name="lat" id="lat" value="{{ old('lat') }}">
                <input type="hidden" name="lng" id="lng" value="{{ old('lng') }}">
            </div>

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium">{{ __('When did it happen?') }}</label>
                    <input type="datetime-local" name="incident_time" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('incident_time', now()->format('Y-m-d\TH:i')) }}" class="mt-1 w-full border-slate-300 rounded-md text-sm">
                    @error('incident_time')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm mt-6"><input type="checkbox" name="is_anonymous" value="1" class="rounded" @checked(old('is_anonymous'))> {{ __('Post anonymously (moderators can still see who sent it)') }}</label>
            </div>

            <p class="text-xs text-slate-500">{{ __('AI will read your report, classify it and check for duplicates. A moderator verifies it before it changes any safety score.') }}</p>
            <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-md py-2.5 font-semibold">{{ __('Submit report') }}</button>
        </form>
    </div>
    @push('scripts')
        <script>
            const map = NL.map('map', NL.DHAKA, 12);
            let marker = null;
            const lat = document.getElementById('lat'), lng = document.getElementById('lng');
            function setPoint(la, ln) {
                if (marker) map.removeLayer(marker);
                marker = L.marker([la, ln]).addTo(map);
                lat.value = la; lng.value = ln;
            }
            if (lat.value && lng.value) setPoint(parseFloat(lat.value), parseFloat(lng.value));
            map.on('click', e => setPoint(e.latlng.lat, e.latlng.lng));
            document.getElementById('venue_id').addEventListener('change', e => {
                const o = e.target.selectedOptions[0];
                if (o && o.dataset.lat) { setPoint(parseFloat(o.dataset.lat), parseFloat(o.dataset.lng)); map.setView([o.dataset.lat, o.dataset.lng], 16); }
            });
            const body = document.getElementById('body'), count = document.getElementById('count');
            const upd = () => count.textContent = body.value.length; body.addEventListener('input', upd); upd();
            const sel = document.getElementById('venue_id');
            if (sel.value && !lat.value) sel.dispatchEvent(new Event('change'));
        </script>
    @endpush
</x-app-layout>
