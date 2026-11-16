<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl">📊 How NightLifeBD Works</h2>
        <p class="text-sm text-slate-500">A transparent breakdown of every algorithm powering the safety scores, route recommendations, and community trust system.</p>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 py-8 space-y-10">

        {{-- Intro --}}
        <div class="bg-gradient-to-br from-indigo-900 to-slate-900 text-white rounded-2xl p-8">
            <h3 class="text-2xl font-extrabold mb-3">🔍 Transparent by Design</h3>
            <p class="text-indigo-200 leading-relaxed">
                NightLifeBD is built on the principle that a safety system must be <strong class="text-white">explainable</strong>
                — not a black box. Every score shown in the app can be traced back to verified community reports,
                time-of-day multipliers, and reporter trust ratings.
            </p>
            <div class="mt-5 grid sm:grid-cols-3 gap-3 text-sm">
                <div class="bg-white/10 rounded-xl p-4"><div class="text-2xl mb-1">🏙️</div><div class="font-bold">Safety Score</div><div class="text-indigo-300 text-xs mt-1">Time-aware venue risk rating 0–100</div></div>
                <div class="bg-white/10 rounded-xl p-4"><div class="text-2xl mb-1">🛣️</div><div class="font-bold">Pareto Routing</div><div class="text-indigo-300 text-xs mt-1">Multi-objective route comparison</div></div>
                <div class="bg-white/10 rounded-xl p-4"><div class="text-2xl mb-1">🤝</div><div class="font-bold">Trust System</div><div class="text-indigo-300 text-xs mt-1">Dynamic reporter credibility weighting</div></div>
            </div>
        </div>

        {{-- 1. Safety Score --}}
        <section>
            <h3 class="text-xl font-bold text-slate-900 mb-1 flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm flex items-center justify-center font-bold flex-shrink-0">1</span>
                Venue Safety Score
            </h3>
            <p class="text-slate-500 text-sm mb-4 ml-9">How a venue rating is calculated per time window.</p>
            <div class="bg-slate-900 text-green-300 font-mono rounded-xl p-5 text-sm mb-4 overflow-x-auto">
<pre>score = 100
       - SUM(incident_weight * severity_penalty * recency_factor)
       + venue_feature_bonus

incident_weight   = (severity / 5) * trust_score * time_match_factor
severity_penalty  = severity^2 / 10        # quadratic - severe events hurt more
recency_factor    = e^(-0.1 * days_old)    # 30-day half-life
time_match_factor = 1.0 (bucket matches) | 0.25 (off-peak)
venue_feature_bonus = +5 (CCTV) + 8 (guard) + (lighting/5 * 10)</pre>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="border bg-indigo-50 border-indigo-200 rounded-xl p-4 text-sm"><div class="font-semibold mb-1">⏱️ Time Buckets</div><p class="text-slate-600">Reports grouped into 4 windows: Day / Evening / Night / Late. A Night bucket report has 4× weight during Night hours.</p></div>
                <div class="border bg-amber-50 border-amber-200 rounded-xl p-4 text-sm"><div class="font-semibold mb-1">📉 Recency Decay</div><p class="text-slate-600">30-day-old reports carry ~5% of today's weight. Prevents stale data dominating scores.</p></div>
                <div class="border bg-green-50 border-green-200 rounded-xl p-4 text-sm"><div class="font-semibold mb-1">🛡️ Venue Features</div><p class="text-slate-600">CCTV (+5pts), guard (+8pts), lighting (up to +10pts). Verified by venue owners.</p></div>
                <div class="border bg-slate-50 border-slate-200 rounded-xl p-4 text-sm"><div class="font-semibold mb-1">📊 Badge Thresholds</div><p class="text-slate-600">≥70 → Safe 🟢 &nbsp;|&nbsp; 40–69 → Moderate 🟡 &nbsp;|&nbsp; &lt;40 → Caution 🔴</p></div>
            </div>
        </section>

        {{-- 2. Pareto Routing --}}
        <section>
            <h3 class="text-xl font-bold text-slate-900 mb-1 flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-green-600 text-white text-sm flex items-center justify-center font-bold flex-shrink-0">2</span>
                Pareto-Optimal Route Comparison
            </h3>
            <p class="text-slate-500 text-sm mb-4 ml-9">Why we show 3 routes instead of 1, and how each is selected.</p>
            <div class="bg-slate-900 text-green-300 font-mono rounded-xl p-5 text-sm mb-4 overflow-x-auto">
<pre>risk_per_km = SUM(report_weights_along_route) / distance_km

# Safety grade A-F
A: risk_per_km < 0.5    D: risk_per_km < 8.0
B: risk_per_km < 1.5    E: risk_per_km < 15.0
C: risk_per_km < 3.0    F: anything worse

detour_pct     = (route_time - fastest_time) / fastest_time * 100
well_lit_score = AVG(lighting_ratings_near_waypoints)</pre>
            </div>
            <div class="grid sm:grid-cols-3 gap-3">
                <div class="rounded-xl border-2 border-slate-200 bg-slate-50 p-4 text-sm"><div class="flex items-center gap-2 mb-2"><span class="text-xl">⚡</span><span class="font-bold text-slate-600">Fastest</span></div><p class="text-slate-600 text-xs">Min duration_s. Baseline reference. May pass risky areas.</p></div>
                <div class="rounded-xl border-2 border-green-200 bg-green-50 p-4 text-sm"><div class="flex items-center gap-2 mb-2"><span class="text-xl">🛡️</span><span class="font-bold text-green-700">Safest</span></div><p class="text-slate-600 text-xs">Min risk_per_km. Avoids incident clusters and dark spots. Capped ≤30% longer.</p></div>
                <div class="rounded-xl border-2 border-purple-200 bg-purple-50 p-4 text-sm"><div class="flex items-center gap-2 mb-2"><span class="text-xl">💡</span><span class="font-bold text-purple-700">Well-Lit</span></div><p class="text-slate-600 text-xs">Max avg lighting score near waypoints. Correlates with CCTV and commercial activity.</p></div>
            </div>
        </section>

        {{-- 3. Trust System --}}
        <section>
            <h3 class="text-xl font-bold text-slate-900 mb-1 flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-amber-500 text-white text-sm flex items-center justify-center font-bold flex-shrink-0">3</span>
                Reporter Trust Score
            </h3>
            <p class="text-slate-500 text-sm mb-4 ml-9">How the system learns to trust some reporters more than others.</p>
            <div class="bg-slate-900 text-green-300 font-mono rounded-xl p-5 text-sm mb-4">
<pre>Initial trust = 0.5  (range: 0.0 → 1.0)

On report VERIFIED:  trust += 0.05 * (1 - trust)  # decaying increase
On report REJECTED:  trust -= 0.15 * trust         # sharper penalty
On DUPLICATE found:  trust -= 0.05 * trust

Badges:
  trust >= 0.8  -> Gold    (high-credibility)
  trust >= 0.6  -> Silver
  trust <  0.6  -> Bronze</pre>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900">
                <strong>Why this matters:</strong> A Gold-trust reporter contributes up to 1.6× more to risk scores than a Bronze reporter. A flood of low-trust spam has limited impact while a single high-trust serious report is meaningfully weighted.
            </div>
        </section>

        {{-- 4. AI Pipeline --}}
        <section>
            <h3 class="text-xl font-bold text-slate-900 mb-1 flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-purple-600 text-white text-sm flex items-center justify-center font-bold flex-shrink-0">4</span>
                AI Report Analysis Pipeline
            </h3>
            <p class="text-slate-500 text-sm mb-4 ml-9">How submitted reports are classified before human moderation.</p>
            <div class="space-y-2">
                @foreach ([
                    ['1','Language detection','Identifies Bangla, English, or Banglish mixed input.','bg-purple-50 border-purple-200'],
                    ['2','Category classification','Maps freetext to: theft, harassment, fighting, poor_lighting, positive, other.','bg-indigo-50 border-indigo-200'],
                    ['3','Severity estimation','Extracts keywords (knife, gun, group attack) to assign severity 1–5.','bg-blue-50 border-blue-200'],
                    ['4','Spam / duplicate detection','Cosine similarity > 0.85 against recent reports flags duplicates automatically.','bg-amber-50 border-amber-200'],
                    ['5','Human moderation','Moderator verifies AI output. Only verified reports feed the scoring system.','bg-green-50 border-green-200'],
                ] as [$step, $title, $desc, $cls])
                <div class="flex gap-3 items-start border {{ $cls }} rounded-xl p-4 text-sm">
                    <span class="w-6 h-6 rounded-full bg-white border text-slate-600 text-xs flex items-center justify-center font-bold flex-shrink-0">{{ $step }}</span>
                    <div><div class="font-semibold text-slate-800">{{ $title }}</div><p class="text-slate-600 mt-0.5">{{ $desc }}</p></div>
                </div>
                @endforeach
            </div>
        </section>

        {{-- 5. Limitations --}}
        <section>
            <h3 class="text-xl font-bold text-slate-900 mb-1 flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-slate-600 text-white text-sm flex items-center justify-center font-bold flex-shrink-0">5</span>
                Known Limitations
            </h3>
            <div class="bg-white border border-slate-200 rounded-xl divide-y divide-slate-100 text-sm">
                @foreach ([
                    ['No official crime dataset','Bangladesh has no public machine-readable crime DB. All risk data is community-sourced, introducing selection bias.'],
                    ['Cold-start problem','New areas with few reporters show Unrated even if genuinely unsafe. Trust scores need time to calibrate.'],
                    ['GPS accuracy in dense Dhaka','In high-rise areas GPS can be offset 30–50m, attributing incidents to the wrong venue.'],
                    ['Routing API dependency','Safe route needs connectivity to OpenRouteService. Offline fallback uses straight-line heuristic.'],
                    ['Fictional demo data','All venues, reports and users in the current deployment are fictional, seeded for demonstration.'],
                ] as [$title, $desc])
                <div class="px-5 py-4">
                    <div class="font-semibold text-slate-700 mb-0.5">⚠️ {{ $title }}</div>
                    <p class="text-slate-500">{{ $desc }}</p>
                </div>
                @endforeach
            </div>
        </section>

        {{-- Footer CTA --}}
        <div class="text-center pt-4 pb-8">
            <a href="{{ route('heatmap.index') }}" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl px-6 py-3 transition-all shadow-md mr-3">🔥 See the live heatmap</a>
            <a href="{{ route('route.index') }}" class="inline-flex items-center gap-2 border border-slate-300 hover:border-slate-400 text-slate-700 font-semibold rounded-xl px-6 py-3 transition-all">🛣️ Try safe route finder</a>
        </div>

    </div>
</x-app-layout>
