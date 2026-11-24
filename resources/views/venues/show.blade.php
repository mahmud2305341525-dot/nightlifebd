<x-app-layout>
    @include('partials.leaflet')
    <div class="max-w-5xl mx-auto p-4 grid md:grid-cols-3 gap-4" x-data="{ formulaModal: false, flagModal: false, flagReportId: null }">
        <div class="md:col-span-2 space-y-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5">
                <div class="flex justify-between items-start gap-3">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $venue->name }}</h1>
                        <p class="text-slate-500 dark:text-slate-400">{{ ucfirst($venue->category) }} · {{ $venue->area }} · {{ str_repeat('৳', $venue->price_tier) }}</p>
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
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <x-badge :badge="$venue->badgeFor($bucket)" class="text-sm" />
                            @php
                                $heroScore = $venue->scoreFor($bucket);
                                $heroConf = $heroScore?->confidence_level ?? 'low';
                                $heroConfPill = match($heroConf) {
                                    'high' => ['icon' => '🟢', 'label' => __('High confidence'), 'cls' => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'],
                                    'moderate' => ['icon' => '🟡', 'label' => __('Moderate confidence'), 'cls' => 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'],
                                    default => ['icon' => '⚪', 'label' => __('Low confidence'), 'cls' => 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700'],
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-0.5 rounded-full border {{ $heroConfPill['cls'] }}" title="{{ $heroScore?->confidence_explanation }}">
                                <span>{{ $heroConfPill['icon'] }}</span>
                                <span>{{ $heroConfPill['label'] }}</span>
                                @if (($heroScore?->evidence_count ?? 0) > 0)
                                    <span class="text-[10px] opacity-75">({{ $heroScore->evidence_count }} {{ __('evidence') }})</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-slate-700 dark:text-slate-300">{{ $venue->description }}</p>
                @if ($venue->source === 'osm')
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Source') }}: <a class="underline" href="{{ $venue->source_url }}" target="_blank" rel="noopener">OpenStreetMap</a>
                        · © OpenStreetMap contributors · {{ __('Unverified; security features are not assumed.') }}
                    </p>
                @elseif ($venue->verification_level === 'owner_verified')
                    <p class="mt-2 text-xs text-emerald-700 dark:text-emerald-300">{{ __('Verified by owner') }} · {{ __('Checklist data is provided by an approved venue owner.') }}</p>
                @endif
                @if ($venue->tagList())
                    <div class="mt-2 flex flex-wrap gap-1">@foreach ($venue->tagList() as $t)<span class="text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-full px-2 py-0.5">{{ $t }}</span>@endforeach</div>
                @endif

                {{-- Average Rating --}}
                @if ($avgRating)
                    <div class="mt-3 flex items-center gap-2 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg px-3 py-2 w-fit">
                        <span class="text-amber-500 text-lg font-bold">★ {{ number_format($avgRating, 1) }}</span>
                        <span class="text-sm text-slate-600 dark:text-slate-300">{{ __(':count review(s)', ['count' => $reviewCount]) }}</span>
                    </div>
                @endif

                <ul class="mt-4 grid grid-cols-2 gap-1 text-sm text-slate-700 dark:text-slate-300">
                    <li>🕐 {{ __('Hours') }}: {{ $venue->opens_at ? substr($venue->opens_at, 0, 5) : '-' }} - {{ $venue->closes_at ? substr($venue->closes_at, 0, 5) : '-' }}</li>
                    <li>💡 {{ __('Lighting') }}: {{ $venue->lighting !== null ? $venue->lighting.'/5' : __('Not yet verified') }}</li>
                    <li>🛡️ {{ __('Security guard') }}: {{ $venue->has_guard === null ? __('Not yet verified') : ($venue->has_guard ? __('Yes') : __('No')) }}</li>
                    <li>📹 CCTV: {{ $venue->has_cctv === null ? __('Not yet verified') : ($venue->has_cctv ? __('Yes') : __('No')) }}</li>
                </ul>
                <div class="mt-4 flex flex-wrap gap-2 items-center">
                    <a href="{{ route('route.index', ['to_lat' => $venue->lat, 'to_lng' => $venue->lng, 'venue_name' => $venue->name]) }}"
                       class="rounded-md bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white px-4 py-2 text-sm font-semibold transition-all flex items-center gap-2 shadow-sm">
                        📍 {{ __('Safe route here') }}
                    </a>
                    <a href="{{ route('reports.create', ['venue' => $venue->id]) }}" class="rounded-md border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 text-slate-700 dark:text-slate-200 px-4 py-2 text-sm font-semibold transition-all">{{ __('Report / review this venue') }}</a>
                </div>
                @auth
                    @if ($venue->owner_id !== auth()->id())
                        <details class="mt-3 border-t border-slate-100 dark:border-slate-800 pt-3">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Own this venue? Submit a claim') }}</summary>
                            <form method="POST" action="{{ route('venues.claim', $venue) }}" enctype="multipart/form-data" class="mt-3 grid sm:grid-cols-2 gap-2 text-sm">
                                @csrf
                                <input type="text" name="phone" maxlength="30" placeholder="{{ __('Business phone') }}" class="rounded-md border-slate-300">
                                <input type="email" name="email" maxlength="190" placeholder="{{ __('Business email') }}" class="rounded-md border-slate-300">
                                <textarea name="note" maxlength="1000" rows="2" placeholder="{{ __('Claim evidence summary') }}" class="sm:col-span-2 rounded-md border-slate-300"></textarea>
                                <input type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf" required class="sm:col-span-2 text-xs">
                                <button type="submit" class="sm:col-span-2 rounded-md bg-slate-800 text-white px-3 py-2">{{ __('Submit venue claim') }}</button>
                            </form>
                        </details>
                    @endif
                @endauth
                <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-500">📡 {{ __('Your GPS location will be auto-detected as the starting point.') }}</p>
            </div>

            {{-- Live Community Pulse (Crowdsourced Lighting & Crowd Gauge) --}}
            <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-xl shadow-sm p-4 space-y-2.5 border border-slate-800" x-data="{
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

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5" x-data="{ formulaModal: false }">
                <div class="flex justify-between items-start gap-2 mb-1">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ __('Why this score? (time-aware, explainable)') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">{{ __('Scores combine the venue checklist with verified, recent, trust-weighted community reports. AI-assisted and advisory only.') }}</p>
                    </div>
                    <button type="button" @click="formulaModal = true"
                        class="text-xs font-semibold px-2.5 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition flex items-center gap-1.5 shrink-0 shadow-sm"
                        title="{{ __('View mathematical breakdown') }}">
                        <span>🔬</span>
                        <span>{{ __('Inspect Formula') }}</span>
                    </button>
                </div>

                {{-- Mathematical Formula Inspector Modal --}}
                <div x-show="formulaModal" @click.self="formulaModal = false" style="display: none;"
                    class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-w-xl w-full p-6 space-y-4 border border-slate-200 dark:border-slate-800 max-h-[90vh] overflow-y-auto">
                        <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-800 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">🔬</span>
                                <h3 class="font-bold text-lg text-slate-800 dark:text-slate-100">{{ __('Safety Score Formula Breakdown') }}</h3>
                            </div>
                            <button @click="formulaModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-lg font-bold p-1">✕</button>
                        </div>

                        <div class="space-y-3 text-xs text-slate-700 dark:text-slate-300">
                            {{-- Step 1 --}}
                            @php
                                $baseChecklist = 40
                                    + ($venue->has_cctv === true ? 20 : 0)
                                    + ($venue->has_guard === true ? 20 : 0)
                                    + ($venue->lighting !== null ? $venue->lighting * 4 : 0);
                            @endphp
                            <div class="bg-slate-50 dark:bg-slate-800/70 rounded-xl p-3 border border-slate-200 dark:border-slate-700">
                                <div class="font-bold text-slate-900 dark:text-slate-100 text-sm mb-1">1. Venue Checklist Baseline (Max 100)</div>
                                <div class="font-mono text-[11px] bg-white dark:bg-slate-900 p-2 rounded border border-slate-200 dark:border-slate-700 text-indigo-700 dark:text-indigo-400">
                                    Base (40) + CCTV ({{ $venue->has_cctv === true ? '+20' : ($venue->has_cctv === null ? 'Not verified' : '+0') }}) + Guard ({{ $venue->has_guard === true ? '+20' : ($venue->has_guard === null ? 'Not verified' : '+0') }}) + Lighting ({{ $venue->lighting !== null ? $venue->lighting.' × 4 = +'.($venue->lighting * 4) : 'Not verified' }}) = <strong>{{ $baseChecklist }} / 100</strong>
                                </div>
                            </div>

                            {{-- Step 2 --}}
                            <div class="bg-slate-50 dark:bg-slate-800/70 rounded-xl p-3 border border-slate-200 dark:border-slate-700">
                                <div class="font-bold text-slate-900 dark:text-slate-100 text-sm mb-1">2. Time-of-Day Adjustment</div>
                                <div class="grid grid-cols-4 gap-1.5 text-center mt-2 font-mono text-[11px]">
                                    <div class="p-1.5 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 {{ $bucket === 'day' ? 'ring-2 ring-indigo-500 font-bold' : '' }}">🌤️ Day<br><span class="text-emerald-600 dark:text-emerald-400">+10</span></div>
                                    <div class="p-1.5 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 {{ $bucket === 'evening' ? 'ring-2 ring-indigo-500 font-bold' : '' }}">🌆 Eve<br><span class="text-slate-500 dark:text-slate-400">±0</span></div>
                                    <div class="p-1.5 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 {{ $bucket === 'night' ? 'ring-2 ring-indigo-500 font-bold' : '' }}">🌙 Night<br><span class="text-amber-600 dark:text-amber-400">−5</span></div>
                                    <div class="p-1.5 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 {{ $bucket === 'late' ? 'ring-2 ring-indigo-500 font-bold' : '' }}">🌃 Late<br><span class="text-red-600 dark:text-red-400">−10</span></div>
                                </div>
                            </div>

                            {{-- Step 3 --}}
                            <div class="bg-slate-50 dark:bg-slate-800/70 rounded-xl p-3 border border-slate-200 dark:border-slate-700">
                                <div class="font-bold text-slate-900 dark:text-slate-100 text-sm mb-1">3. Verified Community Risk Penalty</div>
                                <p class="text-slate-500 dark:text-slate-400 text-[11px] mb-1.5">
                                    Each verified report subtracts penalty: <code>5 × (Severity × Laplace Trust × e^(-age/30d) × Time Weight × Dupe Boost)</code>
                                </p>
                                <div class="bg-white dark:bg-slate-900 p-2.5 rounded border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 space-y-1">
                                    <div>• <strong>Current Verified Reports for this venue:</strong> {{ $reports->count() }} report(s)</div>
                                    <div>• <strong>Corroboration Boost:</strong> Duplicate reports from other users validate authenticity without multiplying penalty artificially.</div>
                                    <div>• <strong>Laplace Trust Weight:</strong> <code>(verified + 1) / (verified + rejected + 2)</code> clamped to [0.10, 0.90].</div>
                                </div>
                            </div>

                            {{-- Step 4 --}}
                            <div class="bg-indigo-50/70 dark:bg-indigo-950/40 rounded-xl p-3 border border-indigo-200 dark:border-indigo-800 text-indigo-900 dark:text-indigo-200">
                                <div class="font-bold text-sm mb-1">4. Final Clamped Score &amp; Badge Thresholds</div>
                                <div class="font-mono text-[11px]">
                                    Final = clamp(Base + TimeAdjust − 5 × Risk, 0, 100)
                                </div>
                                <div class="flex gap-2 mt-2 font-semibold text-[11px]">
                                    <span class="px-2 py-0.5 rounded bg-green-100 dark:bg-green-950/60 text-green-800 dark:text-green-300 border border-green-300 dark:border-green-800">≥ 70: Safe</span>
                                    <span class="px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-800">≥ 45: Moderate</span>
                                    <span class="px-2 py-0.5 rounded bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300 border border-red-300 dark:border-red-800">&lt; 45: Caution</span>
                                </div>
                            </div>

                            {{-- Step 5 --}}
                            <div class="bg-slate-50 dark:bg-slate-800/70 rounded-xl p-3 border border-slate-200 dark:border-slate-700">
                                <div class="font-bold text-slate-900 dark:text-slate-100 text-sm mb-1">5. Evidence Confidence &amp; Abuse Resistance</div>
                                <div class="bg-white dark:bg-slate-900 p-2.5 rounded border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 space-y-1">
                                    <div>• <strong>Confidence Indicator:</strong> Separate from safety score. Measures corroboration strength (independent reporters, recency &lt;60d, verified reports).</div>
                                    <div>• <strong>Sybil &amp; Manipulation Defense:</strong> 24h per-user reporting limit per venue; velocity burst flags; uncorroborated single-reporter penalties are strictly capped.</div>
                                </div>
                            </div>
                        </div>

                        <div class="text-right pt-2 border-t border-slate-200 dark:border-slate-800">
                            <button type="button" @click="formulaModal = false" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600 text-white font-semibold text-xs rounded-xl">
                                {{ __('Close') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    @foreach (\App\Support\TimeBucket::ALL as $b)
                        @php $s = $venue->scoreFor($b); @endphp
                        <div class="rounded-lg border {{ $b === $bucket ? 'border-indigo-400 bg-indigo-50/40 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-700' }} p-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ \App\Support\TimeBucket::label($b) }} @if ($b === $bucket)<span class="text-xs text-indigo-600 dark:text-indigo-400">· {{ __('now') }}</span>@endif</span>
                                <span class="flex items-center gap-2"><span class="text-sm text-slate-600 dark:text-slate-300">{{ $s?->score ?? '-' }}/100</span><x-badge :badge="$s?->badge ?? 'unrated'" /></span>
                            </div>
                            @if ($s)
                                <div class="mt-2 h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden"><div class="h-2 {{ ['safe' => 'bg-green-600', 'moderate' => 'bg-amber-500', 'caution' => 'bg-red-600'][$s->badge] }}" style="width: {{ $s->score }}%"></div></div>
                                <div class="mt-2 flex items-center justify-between text-xs text-slate-600 dark:text-slate-400">
                                    <p class="flex-1">{{ $s->explanation }}</p>
                                    @php
                                        $bConf = $s->confidence_level ?? 'low';
                                        $bConfPill = match($bConf) {
                                            'high' => ['icon' => '🟢', 'label' => __('High'), 'cls' => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'],
                                            'moderate' => ['icon' => '🟡', 'label' => __('Moderate'), 'cls' => 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'],
                                            default => ['icon' => '⚪', 'label' => __('Low'), 'cls' => 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700'],
                                        };
                                    @endphp
                                    <span class="ml-2 inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full border {{ $bConfPill['cls'] }}" title="{{ $s->confidence_explanation }}">
                                        <span>{{ $bConfPill['icon'] }}</span>
                                        <span>{{ $bConfPill['label'] }}</span>
                                        @if (($s->evidence_count ?? 0) > 0)
                                            <span class="text-[10px] opacity-75">({{ $s->evidence_count }})</span>
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Verified Incident Reports --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5">
                <h2 class="font-semibold text-slate-900 dark:text-slate-100 mb-2">{{ __('Recent verified reports') }}</h2>
                @forelse ($reports as $r)
                    <div class="py-2 border-b border-slate-100 dark:border-slate-800 last:border-0 text-sm">
                        <div class="flex justify-between gap-2">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ \App\Models\Report::categoryLabel($r->category) }} @if ($r->category !== 'positive')<span class="text-slate-400 dark:text-slate-500">· {{ __('severity') }} {{ $r->severity }}/5</span>@endif</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $r->incident_time->diffForHumans() }}</span>
                        </div>
                        <p class="text-slate-700 dark:text-slate-300 mt-1">{{ $r->body }}</p>
                        {{-- Photo thumbnails --}}
                        @if ($r->photos->isNotEmpty())
                            <div class="flex gap-2 mt-2">
                                @foreach ($r->photos as $photo)
                                    <a href="{{ $photo->url() }}" target="_blank" class="block">
                                        <img src="{{ $photo->thumbUrl() }}" alt="{{ __('Evidence photo') }}" class="w-16 h-16 object-cover rounded-md border border-slate-200 dark:border-slate-700 hover:border-indigo-400 transition">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        <div class="flex items-center justify-between mt-1 text-xs text-slate-400 dark:text-slate-500">
                            <div>
                                {{ $r->is_anonymous ? __('Anonymous') : ($r->user?->name ?? __('Community member')) }}
                                @if ($r->duplicates_count > 0) · {{ __(':n other people reported the same', ['n' => $r->duplicates_count]) }} @endif
                            </div>
                            @auth
                                <button type="button" @click="flagReportId = {{ $r->id }}; flagModal = true" class="hover:text-red-500 dark:hover:text-red-400 transition flex items-center gap-1 text-[11px]" title="{{ __('Report abuse') }}">
                                    <span>🚩</span>
                                    <span>{{ __('Flag') }}</span>
                                </button>
                            @endauth
                        </div>

                        {{-- Official Venue Responses (Right-of-Reply) --}}
                        @if ($r->approvedResponses->isNotEmpty())
                            @foreach ($r->approvedResponses as $resp)
                                <div class="mt-2.5 p-3 rounded-lg bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/50 text-xs">
                                    <div class="flex items-center justify-between text-indigo-950 dark:text-indigo-200 font-semibold mb-1">
                                        <span class="flex items-center gap-1.5">
                                            <span>🏢</span>
                                            <span>{{ __('Official Venue Response') }}</span>
                                        </span>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 font-normal">{{ $resp->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-slate-700 dark:text-slate-300 leading-relaxed">{{ $resp->response }}</p>
                                </div>
                            @endforeach
                        @endif
                    </div>
                @empty
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 p-3 text-sm">
                        <p class="font-medium text-slate-700 dark:text-slate-300">{{ __('No verified reports yet.') }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('This score is currently based on physical features only. Share an incident or review to improve the evidence base.') }}</p>
                    </div>
                @endforelse
            </div>

            {{-- ⭐ User Reviews & Ratings --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5">
                <div class="flex justify-between items-center mb-3">
                    <h2 class="font-semibold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        ⭐ {{ __('Reviews') }}
                        @if ($avgRating)
                            <span class="text-amber-500 font-bold">{{ number_format($avgRating, 1) }}</span>
                            <span class="text-sm text-slate-400 dark:text-slate-500 font-normal">({{ $reviewCount }})</span>
                        @endif
                    </h2>
                    @auth
                        <a href="{{ route('reports.create', ['venue' => $venue->id]) }}" class="text-xs bg-indigo-600 hover:bg-indigo-500 text-white rounded-md px-3 py-1.5 font-semibold transition">+ {{ __('Write a review') }}</a>
                    @endauth
                </div>

                @if ($avgRating)
                    {{-- Rating distribution bar --}}
                    <div class="mb-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <div class="flex items-center gap-4">
                            <div class="text-center">
                                <div class="text-4xl font-bold text-amber-500">{{ number_format($avgRating, 1) }}</div>
                                <div class="text-amber-400 text-sm">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <span class="{{ $i <= round($avgRating) ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600' }}">★</span>
                                    @endfor
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $reviewCount }} {{ __('review(s)') }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                @forelse ($reviews as $review)
                    <div class="py-3 border-b border-slate-100 dark:border-slate-800 last:border-0">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <span class="font-medium text-sm text-slate-900 dark:text-slate-100">{{ $review->is_anonymous ? __('Anonymous') : $review->user->name }}</span>
                                <span class="text-amber-400 ml-1">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <span class="{{ $i <= $review->rating ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600' }}">★</span>
                                    @endfor
                                </span>
                            </div>
                            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $review->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm text-slate-700 dark:text-slate-300 mt-1">{{ $review->body }}</p>
                        {{-- Review photos --}}
                        @if ($review->photos->isNotEmpty())
                            <div class="flex gap-2 mt-2">
                                @foreach ($review->photos as $photo)
                                    <a href="{{ $photo->url() }}" target="_blank" class="block">
                                        <img src="{{ $photo->thumbUrl() }}" alt="{{ __('Review photo') }}" class="w-14 h-14 object-cover rounded-md border border-slate-200 dark:border-slate-700 hover:border-indigo-400 transition">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No reviews yet. Be the first to review this venue!') }}</p>
                @endforelse
            </div>
        </div>

        <div>
            <div id="map" style="height:320px" class="rounded-xl border border-slate-300 dark:border-slate-700 z-0"></div>
        </div>
    </div>
    {{-- Abuse Reporting Modal --}}
    @auth
        <div x-show="flagModal" @click.self="flagModal = false" style="display: none;"
            class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4 border border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h3 class="font-bold text-base text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <span>🚩</span> {{ __('Report Abuse / False Information') }}
                    </h3>
                    <button @click="flagModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-lg font-bold p-1">✕</button>
                </div>

                <form method="POST" :action="'/reports/' + flagReportId + '/flag'" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ __('Why are you reporting this report?') }}</label>
                        <select name="reason" required class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                            @foreach (\App\Models\ReportFlag::REASONS as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ __('Additional details (optional)') }}</label>
                        <textarea name="details" rows="3" maxlength="500" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200" placeholder="{{ __('Provide context to help our moderation team review this…') }}"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                        <button type="button" @click="flagModal = false" class="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200">{{ __('Cancel') }}</button>
                        <button type="submit" class="px-4 py-1.5 bg-red-600 hover:bg-red-500 text-white font-semibold text-xs rounded-lg shadow-sm">
                            {{ __('Submit Flag') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endauth

    @push('scripts')
        <script>
            const map = NL.map('map', [{{ $venue->lat }}, {{ $venue->lng }}], 16);
            NL.dot(map, {{ $venue->lat }}, {{ $venue->lng }}, NL.colors[@json($venue->badgeFor($bucket))], @json($venue->name));
        </script>
    @endpush
</x-app-layout>
