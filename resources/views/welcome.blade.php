<x-app-layout>

    {{-- ════════════════════════════════════════════
         HERO SECTION
    ════════════════════════════════════════════ --}}
    <section class="relative bg-slate-900 text-white overflow-hidden">
        {{-- Subtle grid overlay --}}
        <div class="absolute inset-0 opacity-10" style="background-image:linear-gradient(rgba(99,102,241,.4) 1px,transparent 1px),linear-gradient(90deg,rgba(99,102,241,.4) 1px,transparent 1px);background-size:40px 40px;"></div>

        <div class="relative max-w-6xl mx-auto px-4 py-16 sm:py-24 grid md:grid-cols-2 gap-12 items-center">
            <div>
                <p class="text-indigo-300 text-sm font-semibold tracking-widest uppercase">{{ __('Bangla-first AI safety intelligence') }}</p>
                <h1 class="mt-3 text-4xl sm:text-5xl font-extrabold leading-tight tracking-tight">
                    {{ __('Know how safe the night is before you go out.') }}
                </h1>
                <p class="mt-5 text-slate-300 text-lg leading-relaxed">
                    {{ __('People report in Bangla, English or Banglish. AI reads, checks and scores every report — giving you time-aware venue safety, safer routes, and a night plan that avoids risky places.') }}
                </p>

                {{-- Live community stats — graceful fallback in test/fresh-install environments --}}
                @php
                    try {
                        $totalReports = \App\Models\Report::verified()->count();
                        $totalVenues  = \App\Models\Venue::approved()->count();
                        $totalUsers   = \App\Models\User::count();
                    } catch (\Throwable) {
                        $totalReports = $totalVenues = $totalUsers = 0;
                    }
                @endphp
                <div class="mt-6 flex flex-wrap gap-4 text-sm">
                    <div class="bg-white/10 rounded-lg px-4 py-2">
                        <span class="font-bold text-xl text-white">{{ $totalReports }}</span>
                        <span class="text-slate-300 ml-1">{{ __('verified reports') }}</span>
                    </div>
                    <div class="bg-white/10 rounded-lg px-4 py-2">
                        <span class="font-bold text-xl text-white">{{ $totalVenues }}</span>
                        <span class="text-slate-300 ml-1">{{ __('venues scored') }}</span>
                    </div>
                    <div class="bg-white/10 rounded-lg px-4 py-2">
                        <span class="font-bold text-xl text-white">{{ $totalUsers }}</span>
                        <span class="text-slate-300 ml-1">{{ __('community members') }}</span>
                    </div>
                </div>

                {{-- CTA buttons --}}
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('venues.index') }}"
                       class="rounded-lg bg-indigo-500 hover:bg-indigo-400 active:scale-95 px-6 py-3 font-semibold transition-all duration-150 shadow-lg">
                        🏙️ {{ __('Explore venues') }}
                    </a>
                    <a href="{{ route('route.index') }}"
                       class="rounded-lg bg-white/10 hover:bg-white/20 active:scale-95 px-6 py-3 font-semibold transition-all duration-150">
                        🛣️ {{ __('Find a safer route') }}
                    </a>
                    @guest
                        <a href="{{ route('register') }}"
                           class="rounded-lg border border-white/30 hover:border-white/60 active:scale-95 px-6 py-3 font-semibold transition-all duration-150">
                            {{ __('Create free account') }}
                        </a>
                    @endguest
                </div>
            </div>

            {{-- Feature cards --}}
            <div class="grid grid-cols-2 gap-3 text-sm">
                @foreach ([
                    ['🧠', __('AI Report Analyzer'), __('Bangla / Banglish reports become category, severity and spam score — with a Bangla keyword fallback when offline.')],
                    ['🕐', __('Time-aware score'), __('A place can be fine at 8 PM and risky at 1 AM. We show all four time buckets with explainable reasons.')],
                    ['🛣️', __('Safest route'), __('Alternatives are scored on verified incidents, dark spots and nearby police / hospitals. Never >30% longer.')],
                    ['🆘', __('SOS toolkit'), __('Long-press SOS, live trip link, safety timer and fake call. GPS location included. Works offline.')],
                ] as [$icon, $t, $d])
                    <div class="rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 hover:border-indigo-400/40 transition duration-200 p-4 cursor-default">
                        <div class="text-3xl mb-2">{{ $icon }}</div>
                        <div class="font-semibold">{{ $t }}</div>
                        <div class="text-slate-400 mt-1 text-xs leading-relaxed">{{ $d }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════
         HOW IT WORKS — 3-step flow
    ════════════════════════════════════════════ --}}
    <section class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 py-14">
            <h2 class="text-2xl font-bold text-center text-slate-900 mb-10">{{ __('How it works') }}</h2>
            <div class="grid sm:grid-cols-3 gap-8 text-center">
                @foreach ([
                    ['📝', '1. ' . __('Report'), __('Share an incident or review in Bangla, English or Banglish. Anonymous reporting is available. Add a map pin for precision.')],
                    ['🤖', '2. ' . __('AI + moderators verify'), __('AI classifies category, severity, detects duplicates and spam. A moderator verifies. Reporter trust is updated automatically.')],
                    ['🌙', '3. ' . __('Everyone gets safer plans'), __('Only verified, recent, trust-weighted reports feed the safety scores, heatmap, route finder and Night Planner.')],
                ] as [$icon, $title, $desc])
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center text-2xl">{{ $icon }}</div>
                        <div class="font-bold text-base text-slate-900">{{ $title }}</div>
                        <p class="text-slate-600 text-sm leading-relaxed">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ════════════════════════════════════════════
         FEATURE HIGHLIGHTS — quick navigation row
    ════════════════════════════════════════════ --}}
    <section class="max-w-6xl mx-auto px-4 py-12">
        <h2 class="text-xl font-bold text-slate-900 mb-6">{{ __('Explore the platform') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ([
                [route('heatmap.index'),   '🔥', __('Safety heatmap'),    __('Hour-by-hour risk map of Dhaka. Drag the slider to see how risk changes.'), 'bg-orange-50 border-orange-200 hover:border-orange-400'],
                [route('planner.index'),   '🌙', __('Night Planner'),     __('Describe your night in Bangla. AI picks 2–3 safe stops within your budget.'), 'bg-indigo-50 border-indigo-200 hover:border-indigo-400'],
                [route('near-me'),         '🏥', __('Near Me'),           __('Nearest police stations, hospitals and 24-h pharmacies from your GPS position.'), 'bg-blue-50 border-blue-200 hover:border-blue-400'],
                [route('reports.create'),  '📝', __('Report an incident'), __('Submit an incident or positive review. Helps keep safety scores accurate.'), 'bg-green-50 border-green-200 hover:border-green-400'],
            ] as [$href, $icon, $title, $desc, $cls])
                <a href="{{ $href }}" class="border {{ $cls }} rounded-xl p-4 block transition duration-150 group">
                    <div class="text-3xl mb-2 group-hover:scale-110 transition-transform duration-200 inline-block">{{ $icon }}</div>
                    <div class="font-semibold text-slate-900">{{ $title }}</div>
                    <p class="text-slate-600 text-xs mt-1 leading-relaxed">{{ $desc }}</p>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ════════════════════════════════════════════
         DISCLAIMER BANNER
    ════════════════════════════════════════════ --}}
    <section class="max-w-6xl mx-auto px-4 pb-10">
        <div class="rounded-xl bg-amber-50 border border-amber-200 px-5 py-4 text-sm text-amber-900 flex gap-3 items-start">
            <span class="text-lg shrink-0">⚠️</span>
            <p>{{ __('Safety information is community-sourced and AI-assisted. It is advisory only and never replaces calling') }} <strong>999</strong>.
                @if (config('nightlife.demo_enabled'))
                    {{ __('Demo mode is enabled: venues, reports and users may be fictional.') }}
                @else
                    {{ __('Real-mode data may come from OpenStreetMap, community reports and approved venue owners.') }}
                @endif
                <a href="{{ route('how-it-works') }}" class="underline font-semibold ml-2">📊 {{ __('How our algorithms work →') }}</a>
            </p>
        </div>
    </section>

</x-app-layout>
