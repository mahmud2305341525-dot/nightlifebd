<x-app-layout>
    <section class="bg-slate-900 text-white">
        <div class="max-w-6xl mx-auto px-4 py-14 sm:py-20 grid md:grid-cols-2 gap-10 items-center">
            <div>
                <p class="text-indigo-300 text-sm font-semibold tracking-wide uppercase">{{ __('Bangla-first AI safety intelligence') }}</p>
                <h1 class="mt-3 text-4xl sm:text-5xl font-extrabold leading-tight">{{ __('Know how safe the night is before you go out.') }}</h1>
                <p class="mt-4 text-slate-300 text-lg">{{ __('People report in Bangla, English or Banglish. AI reads, checks and scores every report, so you get time-aware venue safety, safer routes and a night plan that avoids risky places.') }}</p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('venues.index') }}" class="rounded-lg bg-indigo-500 hover:bg-indigo-400 px-5 py-3 font-semibold">{{ __('Explore venues') }}</a>
                    <a href="{{ route('route.index') }}" class="rounded-lg bg-white/10 hover:bg-white/20 px-5 py-3 font-semibold">{{ __('Find a safer route') }}</a>
                    @guest
                        <a href="{{ route('register') }}" class="rounded-lg border border-white/30 px-5 py-3 font-semibold">{{ __('Create free account') }}</a>
                    @endguest
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 text-sm">
                @foreach ([
                    ['🧠', __('AI Report Analyzer'), __('Bangla / Banglish reports become category, severity and spam score.')],
                    ['🕐', __('Time-aware score'), __('A place can be fine at 8 PM and risky at 1 AM. We show both, with the reason.')],
                    ['🛣️', __('Safest route'), __('Alternatives are scored on incidents, dark spots and nearby police / hospitals.')],
                    ['🆘', __('SOS toolkit'), __('Long-press SOS, live trip link, safety timer and fake call. Works offline.')],
                ] as [$icon, $t, $d])
                    <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                        <div class="text-2xl">{{ $icon }}</div>
                        <div class="font-semibold mt-1">{{ $t }}</div>
                        <div class="text-slate-400 mt-1">{{ $d }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 py-12 grid sm:grid-cols-3 gap-6 text-sm">
        <div><div class="font-bold text-base">1. {{ __('Report') }}</div><p class="text-slate-600 mt-1">{{ __('Share an incident or review in your own language. Anonymous reporting is available.') }}</p></div>
        <div><div class="font-bold text-base">2. {{ __('AI + moderators verify') }}</div><p class="text-slate-600 mt-1">{{ __('AI classifies, detects duplicates and spam; a moderator verifies; reporter trust is updated.') }}</p></div>
        <div><div class="font-bold text-base">3. {{ __('Everyone gets safer plans') }}</div><p class="text-slate-600 mt-1">{{ __('Scores, heatmap, routes and the Night Planner use only verified, recent, trust-weighted reports.') }}</p></div>
    </section>
</x-app-layout>
