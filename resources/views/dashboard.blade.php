<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h2>
        <p class="text-sm text-slate-500">{{ \App\Support\TimeBucket::label($bucket) }} · {{ now()->format('D, d M Y') }}</p>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        {{-- Missing contacts alert --}}
        @if ($contactCount === 0)
            <div class="rounded-lg bg-amber-50 border border-amber-300 p-4 text-sm text-amber-900 flex gap-3 items-center">
                <span class="text-xl">⚠️</span>
                <div>
                    {{ __('Please configure at least one emergency contact so SOS, trips and the safety timer can reach someone.') }}
                    <a href="{{ route('contacts.index') }}" class="underline font-semibold ml-1">{{ __('Add contacts now') }}</a>
                </div>
            </div>
        @endif

        {{-- Quick access grid — SOS prominent at top --}}
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-500 mb-3">{{ __('Quick access') }}</h3>

            {{-- SOS — full width, red, prominent --}}
            <a href="{{ route('sos.index') }}"
               class="flex items-center justify-between bg-red-600 hover:bg-red-500 active:scale-[.98] text-white rounded-xl shadow-md px-6 py-4 mb-3 transition-all duration-150">
                <div class="flex items-center gap-3">
                    <span class="text-3xl">🆘</span>
                    <div>
                        <div class="font-bold text-lg leading-none">{{ __('SOS Emergency') }}</div>
                        <div class="text-sm text-red-200 mt-0.5">{{ __('Long-press · Live trip · Fake call · 999') }}</div>
                    </div>
                </div>
                <svg class="w-6 h-6 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>

            {{-- Secondary tiles grid --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ([
                    ['reports.create', '📝', __('Report'),        'bg-white hover:bg-slate-50'],
                    ['route.index',    '🛣️', __('Safe Route'),   'bg-white hover:bg-slate-50'],
                    ['planner.index',  '🌙', __('Night Planner'), 'bg-indigo-50 hover:bg-indigo-100 border border-indigo-100'],
                    ['trips.index',    '📍', __('Live trip'),     'bg-white hover:bg-slate-50'],
                    ['timer.index',    '⏱️', __('Safety timer'),  'bg-white hover:bg-slate-50'],
                    ['fakecall',       '📞', __('Fake call'),     'bg-white hover:bg-slate-50'],
                    ['near-me',        '🏥', __('Near Me'),       'bg-blue-50 hover:bg-blue-100 border border-blue-100'],
                    ['heatmap.index',  '🔥', __('Heatmap'),       'bg-orange-50 hover:bg-orange-100 border border-orange-100'],
                ] as [$r, $icon, $label, $cls])
                    <a href="{{ route($r) }}" class="{{ $cls }} rounded-xl shadow-sm p-4 text-center transition-all duration-150 active:scale-95">
                        <div class="text-3xl">{{ $icon }}</div>
                        <div class="mt-1 text-sm font-semibold">{{ $label }}</div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Active tools + caution venues --}}
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold mb-3 flex items-center gap-1">🛡️ {{ __('Active safety tools') }}</h3>
                @if ($activeTrip)
                    <div class="flex items-center justify-between text-sm py-2 border-b">
                        <span>📍 {{ __('Live trip running until :t', ['t' => $activeTrip->expires_at->format('h:i A')]) }}</span>
                        <a class="text-indigo-600 underline text-xs font-semibold" href="{{ route('trips.show', $activeTrip->token) }}">{{ __('open') }}</a>
                    </div>
                @endif
                @if ($timer)
                    <div class="flex items-center justify-between text-sm py-2 border-b">
                        <span>⏱️ {{ __('Safety timer due :t', ['t' => $timer->due_at->format('h:i A')]) }}</span>
                        <a class="text-indigo-600 underline text-xs font-semibold" href="{{ route('timer.index') }}">{{ __('open') }}</a>
                    </div>
                @endif
                @if (! $activeTrip && ! $timer)
                    <p class="text-sm text-slate-500">{{ __('Nothing running. Start a live trip or a safety timer before you head out.') }}</p>
                @endif
            </div>

            <div class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold mb-3 flex items-center gap-1">⚠️ {{ __('Venues to avoid right now') }}</h3>
                @forelse ($cautionVenues as $v)
                    <a href="{{ route('venues.show', $v) }}" class="flex justify-between text-sm py-2 border-b last:border-0 hover:text-red-700 transition">
                        <span>{{ $v->name }} <span class="text-slate-400">· {{ $v->area }}</span></span>
                        <x-badge badge="caution" />
                    </a>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No venue has a caution rating at this hour.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- 🔴 LIVE INCIDENT FEED --}}
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-semibold flex items-center gap-2">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                    </span>
                    {{ __('Live Incident Feed') }}
                    <span id="feed-badge" class="hidden text-xs bg-red-100 text-red-700 border border-red-200 font-bold px-2 py-0.5 rounded-full"></span>
                </h3>
                <div class="flex items-center gap-3">
                    <span id="feed-time" class="text-xs text-slate-400"></span>
                    <a href="{{ route('reports.create') }}" class="text-xs bg-indigo-600 hover:bg-indigo-500 text-white rounded-md px-3 py-1.5 font-semibold transition">+ {{ __('Report') }}</a>
                </div>
            </div>

            <div id="feed-list" class="space-y-2 min-h-[60px]">
                <div class="flex items-center gap-2 text-sm text-slate-400 py-4 justify-center">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                    {{ __('Loading feed…') }}
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3">{{ __('Showing verified reports from the last 2 hours · auto-refreshes every 60 s') }}</p>
        </div>

        {{-- My latest reports --}}
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-semibold">{{ __('My latest reports') }}</h3>
                <a href="{{ route('reports.mine') }}" class="text-sm text-indigo-600 underline">{{ __('All reports') }}</a>
            </div>
            @forelse ($reports as $r)
                <div class="text-sm py-2 border-b last:border-0 flex justify-between gap-3">
                    <span class="truncate text-slate-700">{{ \Illuminate\Support\Str::limit($r->body, 80) }}</span>
                    <span class="shrink-0 text-xs px-2 py-0.5 rounded-full {{ ['pending' => 'bg-amber-100 text-amber-800', 'verified' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$r->status] }}">{{ __(ucfirst($r->status)) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('You have not submitted any report yet.') }}</p>
            @endforelse
        </div>

    </div>

    @push('scripts')
    <script>
        const catIcon = { theft:'🦹', harassment:'😤', fighting:'🥊', poor_lighting:'🌑', positive:'💚', other:'⚠️' };
        const catLabel = @json(\App\Models\Report::CATEGORIES ?? []);
        let prevCount = 0;

        async function refreshFeed() {
            try {
                const res = await fetch('{{ route('api.feed') }}');
                const data = await res.json();
                const list = document.getElementById('feed-list');
                const badge = document.getElementById('feed-badge');

                // New reports badge
                if (prevCount > 0 && data.length > prevCount) {
                    const diff = data.length - prevCount;
                    badge.textContent = '+' + diff + ' {{ __('new') }}';
                    badge.classList.remove('hidden');
                    setTimeout(() => badge.classList.add('hidden'), 8000);
                }
                prevCount = data.length;

                document.getElementById('feed-time').textContent = '{{ __('Updated') }} ' + new Date().toLocaleTimeString('en-BD', { hour:'2-digit', minute:'2-digit' });

                if (!data.length) {
                    list.innerHTML = '<p class="text-sm text-slate-500 py-3 text-center">{{ __('No verified incident reports in the last 2 hours. The area looks calm! ✅') }}</p>';
                    return;
                }

                list.innerHTML = data.map(r => `
                    <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-50 hover:bg-slate-100 transition text-sm">
                        <span class="text-xl flex-shrink-0 mt-0.5">${catIcon[r.category] || '⚠️'}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex justify-between gap-2 flex-wrap">
                                <span class="font-semibold text-slate-800">${NL.esc(r.category_label)}</span>
                                <span class="text-xs text-slate-400 flex-shrink-0">${NL.esc(r.time_ago)}</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                ${r.venue_name ? '📍 ' + NL.esc(r.venue_name) + ' · ' : ''}${NL.esc(r.area || '')}
                                ${r.severity > 3 ? '<span class="ml-1 text-red-600 font-semibold">⬆ {{ __('High severity') }}</span>' : ''}
                            </div>
                            <p class="text-slate-600 mt-1 line-clamp-2">${NL.esc(r.body)}</p>
                        </div>
                        <div class="flex-shrink-0">
                            ${'⭐'.repeat(Math.round(r.severity))}
                        </div>
                    </div>`).join('');
            } catch(e) {
                document.getElementById('feed-list').innerHTML = '<p class="text-sm text-red-500 py-2">{{ __('Could not load feed.') }}</p>';
            }
        }

        refreshFeed();
        setInterval(refreshFeed, 60000); // auto-refresh every 60 s
    </script>
    @endpush
</x-app-layout>

