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
                    <div class="flex items-center gap-2">
                        @auth
                            <form method="POST" action="{{ route('venues.favorite', $venue) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-2xl transition-transform hover:scale-110 active:scale-95 focus:outline-none" title="{{ $isFavorited ? __('Remove from favorites') : __('Add to favorites') }}">
                                    {!! $isFavorited ? '❤️' : '🤍' !!}
                                </button>
                            </form>
                        @endauth
                        <x-badge :badge="$venue->badgeFor($bucket)" class="text-sm" />
                    </div>
                </div>
                <p class="mt-3 text-slate-700">{{ $venue->description }}</p>
                @if ($venue->tagList())
                    <div class="mt-2 flex flex-wrap gap-1">@foreach ($venue->tagList() as $t)<span class="text-xs bg-slate-100 rounded-full px-2 py-0.5">{{ $t }}</span>@endforeach</div>
                @endif

                {{-- Average Rating --}}
                @if ($avgRating)
                    <div class="mt-3 flex items-center gap-2 bg-amber-50 rounded-lg px-3 py-2 w-fit">
                        <span class="text-amber-500 text-lg font-bold">★ {{ number_format($avgRating, 1) }}</span>
                        <span class="text-sm text-slate-600">{{ __(':count review(s)', ['count' => $reviewCount]) }}</span>
                    </div>
                @endif

                <ul class="mt-4 grid grid-cols-2 gap-1 text-sm text-slate-700">
                    <li>🕐 {{ __('Hours') }}: {{ $venue->opens_at ? substr($venue->opens_at, 0, 5) : '-' }} - {{ $venue->closes_at ? substr($venue->closes_at, 0, 5) : '-' }}</li>
                    <li>💡 {{ __('Lighting') }}: {{ $venue->lighting }}/5</li>
                    <li>🛡️ {{ __('Security guard') }}: {{ $venue->has_guard ? __('Yes') : __('No') }}</li>
                    <li>📹 CCTV: {{ $venue->has_cctv ? __('Yes') : __('No') }}</li>
                </ul>
                <div class="mt-4 flex flex-wrap gap-2 items-center">
                    <a href="{{ route('route.index', ['to_lat' => $venue->lat, 'to_lng' => $venue->lng, 'venue_name' => $venue->name]) }}"
                       class="rounded-md bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white px-4 py-2 text-sm font-semibold transition-all flex items-center gap-2 shadow-sm">
                        📍 {{ __('Safe route here') }}
                    </a>
                    <a href="{{ route('reports.create', ['venue' => $venue->id]) }}" class="rounded-md border border-slate-300 hover:border-slate-400 px-4 py-2 text-sm font-semibold transition-all">{{ __('Report / review this venue') }}</a>
                </div>
                <p class="mt-1.5 text-xs text-slate-400">📡 {{ __('Your GPS location will be auto-detected as the starting point.') }}</p>
            </div>

            {{-- Live Community Pulse (Crowdsourced Lighting & Crowd Gauge) --}}
            <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-xl shadow-sm p-4 space-y-2.5" x-data="{
                voted: localStorage.getItem('pulse_voted_{{ $venue->id }}') || false,
                lighting: localStorage.getItem('pulse_light_{{ $venue->id }}') || 'well',
                crowd: localStorage.getItem('pulse_crowd_{{ $venue->id }}') || 'moderate',
                vote(type, val) {
                    if (type === 'light') this.lighting = val;
                    if (type === 'crowd') this.crowd = val;
                    this.voted = true;
                    localStorage.setItem('pulse_voted_{{ $venue->id }}', 'true');
                    localStorage.setItem('pulse_' + type + '_{{ $venue->id }}', val);
                }
            }">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-base">📡</span>
                        <span class="font-bold text-xs uppercase tracking-wider text-amber-400">{{ __('Live Night Pulse (Community Grounding)') }}</span>
                    </div>
                    <span class="text-[11px] text-slate-400" x-show="voted">✅ {{ __('Pulse recorded tonight') }}</span>
                    <span class="text-[11px] text-slate-400" x-show="!voted">{{ __('1-Tap feedback') }}</span>
                </div>

                <div class="grid sm:grid-cols-2 gap-2 text-xs">
                    {{-- Lighting Poll --}}
                    <div class="bg-slate-800/80 rounded-lg p-2.5 border border-slate-700/70">
                        <div class="text-[11px] text-slate-300 font-medium mb-1.5 flex items-center justify-between">
                            <span>💡 {{ __('Lighting Right Now') }}</span>
                            <span class="text-[10px] text-amber-300 font-bold" x-text="lighting === 'well' ? '{{ __('Well-lit (উজ্জ্বল)') }}' : '{{ __('Dim/Dark (অন্ধকার)') }}'"></span>
                        </div>
                        <div class="grid grid-cols-2 gap-1.5">
                            <button type="button" @click="vote('light', 'well')"
                                :class="lighting === 'well' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
                                class="py-1 px-2 rounded text-[11px] transition text-center">
                                💡 {{ __('Well-lit') }}
                            </button>
                            <button type="button" @click="vote('light', 'dim')"
                                :class="lighting === 'dim' ? 'bg-red-600 text-white font-bold' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
                                class="py-1 px-2 rounded text-[11px] transition text-center">
                                🌑 {{ __('Dim / Dark') }}
                            </button>
                        </div>
                    </div>

                    {{-- Crowd Poll --}}
                    <div class="bg-slate-800/80 rounded-lg p-2.5 border border-slate-700/70">
                        <div class="text-[11px] text-slate-300 font-medium mb-1.5 flex items-center justify-between">
                            <span>👥 {{ __('Crowd Level Right Now') }}</span>
                            <span class="text-[10px] text-emerald-300 font-bold" x-text="crowd === 'packed' ? '{{ __('Busy (জনবহুল)') }}' : (crowd === 'empty' ? '{{ __('Desolate (জনশূন্য)') }}' : '{{ __('Normal (স্বাভাবিক)') }}')"></span>
                        </div>
                        <div class="grid grid-cols-3 gap-1">
                            <button type="button" @click="vote('crowd', 'packed')"
                                :class="crowd === 'packed' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
                                class="py-1 px-1 rounded text-[10px] transition text-center truncate">
                                👥 {{ __('Busy') }}
                            </button>
                            <button type="button" @click="vote('crowd', 'moderate')"
                                :class="crowd === 'moderate' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
                                class="py-1 px-1 rounded text-[10px] transition text-center truncate">
                                🌿 {{ __('Normal') }}
                            </button>
                            <button type="button" @click="vote('crowd', 'empty')"
                                :class="crowd === 'empty' ? 'bg-amber-600 text-white font-bold' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'"
                                class="py-1 px-1 rounded text-[10px] transition text-center truncate">
                                ⚠️ {{ __('Empty') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-5" x-data="{ formulaModal: false }">
                <div class="flex justify-between items-start gap-2 mb-1">
                    <div>
                        <h2 class="font-semibold">{{ __('Why this score? (time-aware, explainable)') }}</h2>
                        <p class="text-xs text-slate-500 mb-3">{{ __('Scores combine the venue checklist with verified, recent, trust-weighted community reports. AI-assisted and advisory only.') }}</p>
                    </div>
                    <button type="button" @click="formulaModal = true"
                        class="text-xs font-semibold px-2.5 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition flex items-center gap-1.5 shrink-0 shadow-sm"
                        title="{{ __('View mathematical breakdown') }}">
                        <span>🔬</span>
                        <span>{{ __('Inspect Formula') }}</span>
                    </button>
                </div>

                {{-- Mathematical Formula Inspector Modal --}}
                <div x-show="formulaModal" @click.self="formulaModal = false" style="display: none;"
                    class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full p-6 space-y-4 border border-slate-200 max-h-[90vh] overflow-y-auto">
                        <div class="flex justify-between items-center border-b pb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">🔬</span>
                                <h3 class="font-bold text-lg text-slate-800">{{ __('Safety Score Formula Breakdown') }}</h3>
                            </div>
                            <button @click="formulaModal = false" class="text-slate-400 hover:text-slate-700 text-lg font-bold p-1">✕</button>
                        </div>

                        <div class="space-y-3 text-xs text-slate-700">
                            {{-- Step 1 --}}
                            @php
                                $baseChecklist = 40 + ($venue->has_cctv ? 20 : 0) + ($venue->has_guard ? 20 : 0) + ($venue->lighting * 4);
                            @endphp
                            <div class="bg-slate-50 rounded-xl p-3 border border-slate-200">
                                <div class="font-bold text-slate-900 text-sm mb-1">1. Venue Checklist Baseline (Max 100)</div>
                                <div class="font-mono text-[11px] bg-white p-2 rounded border border-slate-200 text-indigo-700">
                                    Base (40) + CCTV ({{ $venue->has_cctv ? '+20' : '+0' }}) + Guard ({{ $venue->has_guard ? '+20' : '+0' }}) + Lighting ({{ $venue->lighting }} × 4 = +{{ $venue->lighting * 4 }}) = <strong>{{ $baseChecklist }} / 100</strong>
                                </div>
                            </div>

                            {{-- Step 2 --}}
                            <div class="bg-slate-50 rounded-xl p-3 border border-slate-200">
                                <div class="font-bold text-slate-900 text-sm mb-1">2. Time-of-Day Adjustment</div>
                                <div class="grid grid-cols-4 gap-1.5 text-center mt-2 font-mono text-[11px]">
                                    <div class="p-1.5 rounded bg-white border {{ $bucket === 'day' ? 'ring-2 ring-indigo-500 font-bold' : '' }}">🌤️ Day<br><span class="text-emerald-600">+10</span></div>
                                    <div class="p-1.5 rounded bg-white border {{ $bucket === 'evening' ? 'ring-2 ring-indigo-500 font-bold' : '' }}">🌆 Eve<br><span class="text-slate-500">±0</span></div>
                                    <div class="p-1.5 rounded bg-white border {{ $bucket === 'night' ? 'ring-2 ring-indigo-500 font-bold' : '' }}">🌙 Night<br><span class="text-amber-600">−5</span></div>
                                    <div class="p-1.5 rounded bg-white border {{ $bucket === 'late' ? 'ring-2 ring-indigo-500 font-bold' : '' }}">🌃 Late<br><span class="text-red-600">−10</span></div>
                                </div>
                            </div>

                            {{-- Step 3 --}}
                            <div class="bg-slate-50 rounded-xl p-3 border border-slate-200">
                                <div class="font-bold text-slate-900 text-sm mb-1">3. Verified Community Risk Penalty</div>
                                <p class="text-slate-500 text-[11px] mb-1.5">
                                    Each verified report subtracts penalty: <code>5 × (Severity × Laplace Trust × e^(-age/30d) × Time Weight × Dupe Boost)</code>
                                </p>
                                <div class="bg-white p-2.5 rounded border border-slate-200 text-slate-600">
                                    <div>• <strong>Current Verified Reports for this venue:</strong> {{ $reports->count() }} report(s)</div>
                                    <div>• <strong>Corroboration Boost:</strong> Duplicate reports validate authenticity without multiplying penalty artificially.</div>
                                    <div>• <strong>Laplace Trust Weight:</strong> <code>(verified + 1) / (verified + rejected + 2)</code> prevents freshly created bot accounts from heavily impacting scores.</div>
                                </div>
                            </div>

                            {{-- Step 4 --}}
                            <div class="bg-indigo-50/70 rounded-xl p-3 border border-indigo-200 text-indigo-900">
                                <div class="font-bold text-sm mb-1">4. Final Clamped Score & Badge Thresholds</div>
                                <div class="font-mono text-[11px]">
                                    Final = clamp(Base + TimeAdjust − 5 × Risk, 0, 100)
                                </div>
                                <div class="flex gap-2 mt-2 font-semibold text-[11px]">
                                    <span class="px-2 py-0.5 rounded bg-green-100 text-green-800 border border-green-300">≥ 70: Safe</span>
                                    <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-300">≥ 45: Moderate</span>
                                    <span class="px-2 py-0.5 rounded bg-red-100 text-red-800 border border-red-300">&lt; 45: Caution</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-right pt-2 border-t">
                            <button type="button" @click="formulaModal = false" class="px-4 py-2 bg-slate-800 text-white font-semibold text-xs rounded-xl hover:bg-slate-700">
                                {{ __('Close') }}
                            </button>
                        </div>
                    </div>
                </div>
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

            {{-- Verified Incident Reports --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h2 class="font-semibold mb-2">{{ __('Recent verified reports') }}</h2>
                @forelse ($reports as $r)
                    <div class="py-2 border-b last:border-0 text-sm">
                        <div class="flex justify-between gap-2">
                            <span class="font-medium">{{ \App\Models\Report::categoryLabel($r->category) }} @if ($r->category !== 'positive')<span class="text-slate-400">· {{ __('severity') }} {{ $r->severity }}/5</span>@endif</span>
                            <span class="text-xs text-slate-500">{{ $r->incident_time->diffForHumans() }}</span>
                        </div>
                        <p class="text-slate-700 mt-1">{{ $r->body }}</p>
                        {{-- Photo thumbnails --}}
                        @if ($r->photos->isNotEmpty())
                            <div class="flex gap-2 mt-2">
                                @foreach ($r->photos as $photo)
                                    <a href="{{ $photo->url() }}" target="_blank" class="block">
                                        <img src="{{ $photo->thumbUrl() }}" alt="{{ __('Evidence photo') }}" class="w-16 h-16 object-cover rounded-md border border-slate-200 hover:border-indigo-400 transition">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        <p class="text-xs text-slate-400 mt-1">
                            {{ $r->is_anonymous ? __('Anonymous') : $r->user->name }}
                            @if ($r->duplicates_count > 0) · {{ __(':n other people reported the same', ['n' => $r->duplicates_count]) }} @endif
                        </p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No verified reports yet.') }}</p>
                @endforelse
            </div>

            {{-- ⭐ User Reviews & Ratings --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold flex items-center gap-2">
                        ⭐ {{ __('Reviews') }}
                        @if ($avgRating)
                            <span class="text-amber-500 font-bold">{{ number_format($avgRating, 1) }}</span>
                            <span class="text-sm text-slate-400 font-normal">({{ $reviewCount }})</span>
                        @endif
                    </h2>
                    @auth
                        <a href="{{ route('reports.create', ['venue' => $venue->id]) }}" class="text-xs bg-indigo-600 hover:bg-indigo-500 text-white rounded-md px-3 py-1.5 font-semibold transition">+ {{ __('Write a review') }}</a>
                    @endauth
                </div>

                @if ($avgRating)
                    {{-- Rating distribution bar --}}
                    <div class="mb-4 p-3 rounded-lg bg-slate-50">
                        <div class="flex items-center gap-4">
                            <div class="text-center">
                                <div class="text-4xl font-bold text-amber-500">{{ number_format($avgRating, 1) }}</div>
                                <div class="text-amber-400 text-sm">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <span class="{{ $i <= round($avgRating) ? 'text-amber-400' : 'text-slate-300' }}">★</span>
                                    @endfor
                                </div>
                                <div class="text-xs text-slate-500 mt-1">{{ $reviewCount }} {{ __('review(s)') }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                @forelse ($reviews as $review)
                    <div class="py-3 border-b last:border-0">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <span class="font-medium text-sm">{{ $review->is_anonymous ? __('Anonymous') : $review->user->name }}</span>
                                <span class="text-amber-400 ml-1">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <span class="{{ $i <= $review->rating ? 'text-amber-400' : 'text-slate-300' }}">★</span>
                                    @endfor
                                </span>
                            </div>
                            <span class="text-xs text-slate-400">{{ $review->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm text-slate-700 mt-1">{{ $review->body }}</p>
                        {{-- Review photos --}}
                        @if ($review->photos->isNotEmpty())
                            <div class="flex gap-2 mt-2">
                                @foreach ($review->photos as $photo)
                                    <a href="{{ $photo->url() }}" target="_blank" class="block">
                                        <img src="{{ $photo->thumbUrl() }}" alt="{{ __('Review photo') }}" class="w-14 h-14 object-cover rounded-md border border-slate-200 hover:border-indigo-400 transition">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No reviews yet. Be the first to review this venue!') }}</p>
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
