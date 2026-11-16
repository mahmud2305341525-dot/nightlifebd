<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800">📊 {{ __('Analytics Dashboard') }}</h2>
        <p class="text-sm text-slate-500">{{ __('Platform overview for moderators and admins') }}</p>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        {{-- Top-level stat cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ([
                ['📝', __('Total Reports'), $totalReports, 'bg-white'],
                ['⏳', __('Pending Review'), $pendingReports, $pendingReports > 0 ? 'bg-amber-50 border-amber-200' : 'bg-white'],
                ['🏢', __('Approved Venues'), $venueCount, 'bg-white'],
                ['👥', __('Total Users'), $totalUsers, 'bg-white'],
            ] as [$icon, $label, $value, $cls])
                <div class="rounded-xl shadow-sm border p-4 {{ $cls }}">
                    <div class="text-2xl">{{ $icon }}</div>
                    <div class="text-2xl font-bold mt-1">{{ number_format($value) }}</div>
                    <div class="text-sm text-slate-500">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        {{-- Secondary stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ([
                [__('Verified'), $reportCounts['verified'] ?? 0, 'text-green-700 bg-green-50'],
                [__('Rejected'), $reportCounts['rejected'] ?? 0, 'text-red-700 bg-red-50'],
                [__('Active this week'), $activeUsersWeek, 'text-indigo-700 bg-indigo-50'],
                [__('New this week'), $newUsersWeek, 'text-blue-700 bg-blue-50'],
            ] as [$label, $value, $cls])
                <div class="rounded-xl shadow-sm border p-3 {{ $cls }}">
                    <div class="text-xl font-bold">{{ number_format($value) }}</div>
                    <div class="text-xs font-medium">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        {{-- Charts row --}}
        <div class="grid md:grid-cols-2 gap-4">
            {{-- Reports Trend Chart --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="font-semibold mb-3 flex items-center gap-2">📈 {{ __('Reports Trend (30 days)') }}</h3>
                <canvas id="trendChart" height="200"></canvas>
            </div>

            {{-- Reports by Category --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="font-semibold mb-3 flex items-center gap-2">📊 {{ __('Reports by Category') }}</h3>
                @if (empty($reportsByCategory))
                    <p class="text-sm text-slate-500 py-8 text-center">{{ __('No verified reports in the last 30 days.') }}</p>
                @else
                    <canvas id="categoryChart" height="200"></canvas>
                @endif
            </div>
        </div>

        {{-- Score Distribution & Hotspots --}}
        <div class="grid md:grid-cols-2 gap-4">
            {{-- Safety Score Distribution --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="font-semibold mb-3 flex items-center gap-2">🛡️ {{ __('Safety Score Distribution') }}</h3>
                <canvas id="scoreChart" height="180"></canvas>
                <p class="text-xs text-slate-400 mt-2">{{ __('Night-time safety scores across all approved venues') }}</p>
            </div>

            {{-- Hotspot Venues --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="font-semibold mb-3 flex items-center gap-2">🔥 {{ __('Hotspot Venues (30 days)') }}</h3>
                @forelse ($hotspots as $v)
                    <div class="flex justify-between items-center py-2 border-b last:border-0 text-sm">
                        <div>
                            <span class="font-medium">{{ $v->name }}</span>
                            <span class="text-slate-400">· {{ $v->area }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-semibold">{{ $v->incident_count }} {{ __('incidents') }}</span>
                            <x-badge :badge="$v->badgeFor('night')" />
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 py-4 text-center">{{ __('No incident hotspots detected.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Moderation Performance --}}
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="font-semibold mb-3 flex items-center gap-2">⚡ {{ __('Moderation Performance') }}</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-4 rounded-lg bg-slate-50">
                        <div class="text-3xl font-bold text-indigo-600">{{ $pendingReports }}</div>
                        <div class="text-sm text-slate-500 mt-1">{{ __('Backlog') }}</div>
                    </div>
                    <div class="text-center p-4 rounded-lg bg-slate-50">
                        <div class="text-3xl font-bold text-indigo-600">{{ $avgResolutionHours ?? '—' }}<span class="text-base font-normal text-slate-400">h</span></div>
                        <div class="text-sm text-slate-500 mt-1">{{ __('Avg resolution') }}</div>
                    </div>
                </div>
                @if ($pendingVenues > 0)
                    <div class="mt-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                        ⚠️ {{ trans_choice(':count venue pending approval|:count venues pending approval', $pendingVenues) }}
                        · <a href="{{ route('admin.venues') }}" class="underline font-semibold">{{ __('Review') }}</a>
                    </div>
                @endif
                @if ($bannedUsers > 0)
                    <div class="mt-2 text-xs text-slate-500">🚫 {{ $bannedUsers }} {{ __('banned user(s)') }}</div>
                @endif
            </div>

            {{-- Recent Moderation Actions --}}
            <div class="bg-white rounded-xl shadow-sm p-5">
                <h3 class="font-semibold mb-3 flex items-center gap-2">🕐 {{ __('Recent Moderation Activity') }}</h3>
                @forelse ($recentActions as $log)
                    <div class="flex justify-between items-center py-2 border-b last:border-0 text-sm">
                        <div>
                            <span class="font-medium">{{ $log->user?->name ?? '?' }}</span>
                            <span class="text-slate-500">
                                {{ str_contains($log->action, 'verified') ? __('verified') : __('rejected') }}
                                {{ __('report') }} #{{ $log->meta['report_id'] ?? '?' }}
                            </span>
                        </div>
                        <span class="text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 py-4 text-center">{{ __('No recent moderation actions.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.reports') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-5 py-2.5 text-sm font-semibold transition">📋 {{ __('Moderate reports') }}</a>
            <a href="{{ route('admin.venues') }}" class="bg-white hover:bg-slate-50 border rounded-lg px-5 py-2.5 text-sm font-semibold transition">🏢 {{ __('Approve venues') }}</a>
            @hasrole('super_admin')
                <a href="{{ route('admin.users') }}" class="bg-white hover:bg-slate-50 border rounded-lg px-5 py-2.5 text-sm font-semibold transition">👥 {{ __('Manage users') }}</a>
            @endhasrole
        </div>
    </div>

    @push('head')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    @endpush

    @push('scripts')
    <script>
        // --- Reports Trend (Line) ---
        const trendLabels = @json(array_keys($trendFilled));
        const trendData   = @json(array_values($trendFilled));
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: trendLabels.map(d => d.slice(5)), // MM-DD
                datasets: [{
                    label: '{{ __("Reports") }}',
                    data: trendData,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { maxTicksLimit: 10, font: { size: 10 } } },
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });

        // --- Reports by Category (Doughnut) ---
        @if (!empty($reportsByCategory))
        const catColors = {
            harassment: '#ef4444', theft: '#f59e0b', violence: '#dc2626',
            dark_road: '#64748b', overcharging: '#f97316', positive: '#22c55e', other: '#94a3b8'
        };
        const catLabels = @json(array_keys($reportsByCategory));
        const catData = @json(array_values($reportsByCategory));
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: catLabels.map(c => c.replace('_', ' ')),
                datasets: [{
                    data: catData,
                    backgroundColor: catLabels.map(c => catColors[c] || '#94a3b8'),
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
            }
        });
        @endif

        // --- Safety Score Distribution (Bar) ---
        const scoreBrackets = @json(array_keys($scoreDistribution));
        const scoreCounts = @json(array_values($scoreDistribution));
        const scoreColors = ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e'];
        new Chart(document.getElementById('scoreChart'), {
            type: 'bar',
            data: {
                labels: scoreBrackets,
                datasets: [{
                    label: '{{ __("Venues") }}',
                    data: scoreCounts,
                    backgroundColor: scoreColors,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    </script>
    @endpush
</x-app-layout>
