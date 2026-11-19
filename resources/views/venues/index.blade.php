<x-app-layout>
    @include('partials.leaflet')
    <div class="max-w-6xl mx-auto p-4 space-y-4">
        <form method="GET" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-3 grid grid-cols-2 md:grid-cols-7 gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="{{ __('Search venue') }}" class="border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-md col-span-2 text-sm">
            <select name="category" class="border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-md text-sm">
                <option value="">{{ __('Category') }}</option>
                @foreach ($categories as $c)
                    @continue($c === 'lounge' && ! $adult)
                    <option value="{{ $c }}" @selected(request('category') === $c)>{{ ucfirst($c) }}</option>
                @endforeach
            </select>
            <select name="area" class="border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-md text-sm">
                <option value="">{{ __('Area') }}</option>
                @foreach ($areas as $a)<option @selected(request('area') === $a)>{{ $a }}</option>@endforeach
            </select>
            <select name="price" class="border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-md text-sm">
                <option value="">{{ __('Price') }}</option>
                @foreach ([1 => 'Budget', 2 => 'Mid', 3 => 'High'] as $k => $l)<option value="{{ $k }}" @selected(request('price') == $k)>{{ __($l) }}</option>@endforeach
            </select>
            <select name="badge" class="border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-md text-sm">
                <option value="">{{ __('Safety') }}</option>
                @foreach (['safe', 'moderate', 'caution'] as $b)<option value="{{ $b }}" @selected(request('badge') === $b)>{{ __(ucfirst($b)) }}</option>@endforeach
            </select>
            <select name="when" class="border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 rounded-md text-sm">
                @foreach (\App\Support\TimeBucket::labels() as $k => $l)<option value="{{ $k }}" @selected($bucket === $k)>{{ $l }}</option>@endforeach
            </select>
            <button class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-md p-2 col-span-2 md:col-span-7 text-sm font-semibold">{{ __('Search') }}</button>
        </form>

        @auth
            @unless ($adult)
                <form method="POST" action="{{ route('age.confirm') }}" class="text-sm">
                    @csrf <button class="underline text-slate-600 dark:text-slate-400">{{ __('I am 18 or older - show restricted categories') }}</button>
                </form>
            @endunless
        @endauth

        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('Safety badges shown for: :t', ['t' => \App\Support\TimeBucket::label($bucket)]) }}</p>
        <div id="map" style="height:380px" class="rounded-xl border border-slate-300 dark:border-slate-700 z-0"></div>

        @php
            $badgeCardClass = [
                'safe'     => 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800/60 hover:border-green-400 dark:hover:border-green-600',
                'moderate' => 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800/60 hover:border-amber-400 dark:hover:border-amber-600',
                'caution'  => 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-800/60 hover:border-red-400 dark:hover:border-red-600',
                'unrated'  => 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-700',
            ];
            $venueList = method_exists($venues, 'items') ? $venues->items() : $venues->all();
            $venueMapData = collect($venueList)->map(fn ($v) => [
                'id'    => $v->id,
                'name'  => $v->name,
                'lat'   => $v->lat,
                'lng'   => $v->lng,
                'area'  => $v->area,
                'badge' => $v->badgeFor($bucket),
            ])->values();
        @endphp

        <div class="grid md:grid-cols-2 gap-3">
            @forelse ($venueList as $v)
                @php
                    $badgeKey = $v->badgeFor($bucket);
                    $s = $v->scoreFor($bucket);
                    $cardClass = $badgeCardClass[$badgeKey] ?? $badgeCardClass['unrated'];
                @endphp
                <a href="{{ route('venues.show', $v) }}" class="border {{ $cardClass }} rounded-xl shadow-sm p-4 block transition duration-150">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <div class="font-semibold text-slate-900 dark:text-slate-100">{{ $v->name }}</div>
                            <div class="text-sm text-slate-500 dark:text-slate-400">
                                {{ ucfirst($v->category) }} · {{ $v->area }} · {{ str_repeat('৳', $v->price_tier) }}
                                @php $vAvg = $v->avgRating(); $vRc = $v->reviewCount(); @endphp
                                @if ($vAvg)
                                    · <span class="text-amber-500">★ {{ number_format($vAvg, 1) }}</span> <span class="text-slate-400 dark:text-slate-500">({{ $vRc }})</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <x-badge :badge="$v->badgeFor($bucket)" />
                            @if ($s)<div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $s->score }}/100</div>@endif
                        </div>
                    </div>
                    @if ($s)<p class="text-xs text-slate-500 dark:text-slate-400 mt-2 line-clamp-2">{{ $s->explanation }}</p>@endif
                </a>
            @empty
                <div class="col-span-2 bg-white dark:bg-slate-900 rounded-xl p-8 text-center border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm">
                    <div class="text-4xl">🔍</div>
                    <h3 class="font-bold text-slate-800 dark:text-slate-100 text-sm">{{ __('No venues match your search filters') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                        {{ __('Try adjusting your area, category, or safety badge filters to discover more safe places across Dhaka.') }}
                    </p>
                    <a href="{{ route('venues.index') }}" class="inline-block px-4 py-2 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-semibold text-xs rounded-lg border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition">
                        {{ __('Reset All Filters') }}
                    </a>
                </div>
            @endforelse
        </div>

        {{-- Pagination links (only when not badge-filtered, since badge filter uses a plain Collection) --}}
        @if (method_exists($venues, 'links'))
            <div class="mt-4">{{ $venues->links() }}</div>
        @endif
    </div>

    @push('scripts')
        <script>
            const venues = @json($venueMapData);
            const map = NL.map('map');
            const group = [];
            venues.forEach(v => {
                NL.dot(map, v.lat, v.lng, NL.colors[v.badge] || NL.colors.unrated,
                    `<b>${NL.esc(v.name)}</b><br>${NL.esc(v.area)} · ${NL.esc(v.badge)}<br><a href="/venues/${v.id}">Details</a>`);
                group.push([v.lat, v.lng]);
            });
            if (group.length > 1) map.fitBounds(group, { padding: [30, 30] });
        </script>
    @endpush
</x-app-layout>
