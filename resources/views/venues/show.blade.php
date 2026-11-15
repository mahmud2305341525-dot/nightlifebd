<x-app-layout>
    @include('partials.leaflet')
    <div class="max-w-5xl mx-auto p-4 grid md:grid-cols-3 gap-4">
        <div class="md:col-span-2 space-y-4">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="flex justify-between items-start gap-3">
                    <div>
                        <h1 class="text-2xl font-bold">{{ $venue->name }}</h1>
                        <p class="text-slate-500">{{ ucfirst($venue->category) }} · {{ $venue->area }} · {{ str_repeat('৳', $venue->price_tier) }}</p>
                    </div>
                    <x-badge :badge="$venue->badgeFor($bucket)" class="text-sm" />
                </div>
                <p class="mt-3 text-slate-700">{{ $venue->description }}</p>
                @if ($venue->tagList())
                    <div class="mt-2 flex flex-wrap gap-1">@foreach ($venue->tagList() as $t)<span class="text-xs bg-slate-100 rounded-full px-2 py-0.5">{{ $t }}</span>@endforeach</div>
                @endif
                <ul class="mt-4 grid grid-cols-2 gap-1 text-sm text-slate-700">
                    <li>🕐 {{ __('Hours') }}: {{ $venue->opens_at ? substr($venue->opens_at, 0, 5) : '-' }} - {{ $venue->closes_at ? substr($venue->closes_at, 0, 5) : '-' }}</li>
                    <li>💡 {{ __('Lighting') }}: {{ $venue->lighting }}/5</li>
                    <li>🛡️ {{ __('Security guard') }}: {{ $venue->has_guard ? __('Yes') : __('No') }}</li>
                    <li>📹 CCTV: {{ $venue->has_cctv ? __('Yes') : __('No') }}</li>
                </ul>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('route.index', ['to_lat' => $venue->lat, 'to_lng' => $venue->lng]) }}" class="rounded-md bg-indigo-600 text-white px-4 py-2 text-sm font-semibold">{{ __('Safe route here') }}</a>
                    <a href="{{ route('reports.create', ['venue' => $venue->id]) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold">{{ __('Report / review this venue') }}</a>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold mb-1">{{ __('Why this score? (time-aware, explainable)') }}</h2>
                <p class="text-xs text-slate-500 mb-3">{{ __('Scores combine the venue checklist with verified, recent, trust-weighted community reports. AI-assisted and advisory only.') }}</p>
                <div class="space-y-3">
                    @foreach (\App\Support\TimeBucket::ALL as $b)
                        @php $s = $venue->scoreFor($b); @endphp
                        <div class="rounded-lg border {{ $b === $bucket ? 'border-indigo-400 bg-indigo-50/40' : 'border-slate-200' }} p-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-semibold">{{ \App\Support\TimeBucket::label($b) }} @if ($b === $bucket)<span class="text-xs text-indigo-600">· {{ __('now') }}</span>@endif</span>
                                <span class="flex items-center gap-2"><span class="text-sm text-slate-600">{{ $s?->score ?? '-' }}/100</span><x-badge :badge="$s?->badge ?? 'unrated'" /></span>
                            </div>
                            @if ($s)
                                <div class="mt-2 h-2 rounded-full bg-slate-200 overflow-hidden"><div class="h-2 {{ ['safe' => 'bg-green-600', 'moderate' => 'bg-amber-500', 'caution' => 'bg-red-600'][$s->badge] }}" style="width: {{ $s->score }}%"></div></div>
                                <p class="text-xs text-slate-600 mt-2">{{ $s->explanation }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold mb-2">{{ __('Recent verified reports') }}</h2>
                @forelse ($reports as $r)
                    <div class="py-2 border-b last:border-0 text-sm">
                        <div class="flex justify-between gap-2">
                            <span class="font-medium">{{ \App\Models\Report::categoryLabel($r->category) }} @if ($r->category !== 'positive')<span class="text-slate-400">· {{ __('severity') }} {{ $r->severity }}/5</span>@endif</span>
                            <span class="text-xs text-slate-500">{{ $r->incident_time->diffForHumans() }}</span>
                        </div>
                        <p class="text-slate-700 mt-1">{{ $r->body }}</p>
                        <p class="text-xs text-slate-400 mt-1">
                            {{ $r->is_anonymous ? __('Anonymous') : $r->user->name }}
                            @if ($r->duplicates_count > 0) · {{ __(':n other people reported the same', ['n' => $r->duplicates_count]) }} @endif
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No verified reports yet.') }}</p>
                @endforelse
            </div>
        </div>

        <div>
            <div id="map" style="height:320px" class="rounded-xl border border-slate-300 z-0"></div>
        </div>
    </div>
    @push('scripts')
        <script>
            const map = NL.map('map', [{{ $venue->lat }}, {{ $venue->lng }}], 16);
            NL.dot(map, {{ $venue->lat }}, {{ $venue->lng }}, NL.colors[@json($venue->badgeFor($bucket))], @json($venue->name));
        </script>
    @endpush
</x-app-layout>
