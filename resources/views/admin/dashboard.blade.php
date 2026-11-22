<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100">📊 {{ __('Analytics Dashboard') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Platform overview for moderators and admins') }}</p>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        {{-- Top-level stat cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ([
                ['📝', __('Total Reports'), $totalReports, 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100'],
                ['⏳', __('Pending Review'), $pendingReports, $pendingReports > 0 ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800 text-amber-950 dark:text-amber-200' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100'],
                ['🏢', __('Approved Venues'), $venueCount, 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100'],
                ['👥', __('Total Users'), $totalUsers, 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100'],
            ] as [$icon, $label, $value, $cls])
                <div class="rounded-xl shadow-sm border p-4 {{ $cls }}">
                    <div class="text-2xl">{{ $icon }}</div>
                    <div class="text-2xl font-bold mt-1">{{ number_format($value) }}</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ([
                [__('AI Calls'), $aiUsage['total'], 'text-indigo-700'],
                [__('AI Success'), $aiUsage['success'], 'text-green-700'],
                [__('AI Fallback'), $aiUsage['fallback'], 'text-amber-700'],
                [__('Avg Latency'), $aiUsage['average_latency_ms'].' ms', 'text-slate-700'],
            ] as [$label, $value, $cls])
                <div class="rounded-xl border bg-white dark:bg-slate-900 p-3">
                    <div class="text-xl font-bold {{ $cls }}">{{ $value }}</div>
                    <div class="text-xs text-slate-500">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        {{-- Secondary stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ([
                [__('Verified'), $reportCounts['verified'] ?? 0, 'text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-950/40 border-green-200 dark:border-green-800'],
                [__('Rejected'), $reportCounts['rejected'] ?? 0, 'text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-800'],
                [__('Active this week'), $activeUsersWeek, 'text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-800'],
                [__('New this week'), $newUsersWeek, 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800'],
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
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3 flex items-center gap-2">📈 {{ __('Reports Trend (30 days)') }}</h3>
                <canvas id="trendChart" height="200"></canvas>
            </div>

            {{-- Reports by Category --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3 flex items-center gap-2">📊 {{ __('Reports by Category') }}</h3>
                @if (empty($reportsByCategory))
                    <p class="text-sm text-slate-500 dark:text-slate-400 py-8 text-center">{{ __('No verified reports in the last 30 days.') }}</p>
                @else
                    <canvas id="categoryChart" height="200"></canvas>
                @endif
            </div>
        </div>

        {{-- Score Distribution & Hotspots --}}
        <div class="grid md:grid-cols-2 gap-4">
            {{-- Safety Score Distribution --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3 flex items-center gap-2">🛡️ {{ __('Safety Score Distribution') }}</h3>
                <canvas id="scoreChart" height="180"></canvas>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-2">{{ __('Night-time safety scores across all approved venues') }}</p>
            </div>

            {{-- Hotspot Venues --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3 flex items-center gap-2">🔥 {{ __('Hotspot Venues (30 days)') }}</h3>
                @forelse ($hotspots as $v)
                    <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-800 last:border-0 text-sm">
                        <div>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $v->name }}</span>
                            <span class="text-slate-400 dark:text-slate-500">· {{ $v->area }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300 font-semibold">{{ $v->incident_count }} {{ __('incidents') }}</span>
                            <x-badge :badge="$v->badgeFor('night')" />
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400 py-4 text-center">{{ __('No incident hotspots detected.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Moderation Performance --}}
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3 flex items-center gap-2">⚡ {{ __('Moderation Performance') }}</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ $pendingReports }}</div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Backlog') }}</div>
                    </div>
                    <div class="text-center p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ $avgResolutionHours ?? '—' }}<span class="text-base font-normal text-slate-400 dark:text-slate-500">h</span></div>
                        <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Avg resolution') }}</div>
                    </div>
                </div>
                @if ($pendingVenues > 0)
                    <div class="mt-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 p-3 text-sm text-amber-800 dark:text-amber-200">
                        ⚠️ {{ trans_choice(':count venue pending approval|:count venues pending approval', $pendingVenues) }}
                        · <a href="{{ route('admin.venues') }}" class="underline font-semibold">{{ __('Review') }}</a>
                    </div>
                @endif
                @if ($bannedUsers > 0)
                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">🚫 {{ $bannedUsers }} {{ __('banned user(s)') }}</div>
                @endif
            </div>

            {{-- Recent Moderation Actions --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5">
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 mb-3 flex items-center gap-2">🕐 {{ __('Recent Moderation Activity') }}</h3>
                @forelse ($recentActions as $log)
                    <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-800 last:border-0 text-sm">
                        <div>
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $log->user?->name ?? '?' }}</span>
                            <span class="text-slate-500 dark:text-slate-400">
                                {{ str_contains($log->action, 'verified') ? __('verified') : __('rejected') }}
                                {{ __('report') }} #{{ $log->meta['report_id'] ?? '?' }}
                            </span>
                        </div>
                        <span class="text-xs text-slate-400 dark:text-slate-500">{{ $log->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400 py-4 text-center">{{ __('No recent moderation actions.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- AI vs. Moderator Performance Evaluation Dashboard (Phase 2) --}}
        {{-- ======================================================== --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5 space-y-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-4">
                <div>
                    <h3 class="font-semibold text-lg text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🤖</span> {{ __('AI vs. Moderator Evaluation Dashboard') }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('Empirical classification metrics, confusion matrix, and agreement scores comparing raw AI predictions against human moderator decisions.') }}
                    </p>
                </div>
                {{-- Date and Filter Controls --}}
                <form method="GET" action="{{ route('admin.dashboard') }}" class="flex flex-wrap items-center gap-2 text-xs">
                    <input type="date" name="eval_from" value="{{ $evalFrom }}" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 text-xs py-1 px-2" title="{{ __('From Date') }}">
                    <span class="text-slate-400">→</span>
                    <input type="date" name="eval_to" value="{{ $evalTo }}" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 text-xs py-1 px-2" title="{{ __('To Date') }}">
                    <select name="eval_source" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 text-xs py-1 px-2">
                        <option value="">{{ __('All Analyzers') }}</option>
                        <option value="gemini" {{ $evalSource === 'gemini' ? 'selected' : '' }}>Gemini AI</option>
                        <option value="keyword" {{ $evalSource === 'keyword' ? 'selected' : '' }}>Local Lexicon</option>
                    </select>
                    <select name="eval_category" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 text-xs py-1 px-2">
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach (\App\Models\Report::CATEGORIES as $cat)
                            <option value="{{ $cat }}" {{ $evalCategory === $cat ? 'selected' : '' }}>{{ \App\Models\Report::categoryLabel($cat) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded-md font-medium hover:bg-indigo-500 transition">{{ __('Filter') }}</button>
                    @if ($evalFrom || $evalTo || $evalSource || $evalCategory)
                        <a href="{{ route('admin.dashboard') }}" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 underline py-1">{{ __('Reset') }}</a>
                    @endif
                </form>
            </div>

            {{-- Summary Score Cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <div class="p-3.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Category Accuracy') }}</div>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($evaluation['overall_accuracy'] * 100, 1) }}%</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">{{ __('AI vs Human match') }}</div>
                </div>
                <div class="p-3.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Macro F1-Score') }}</div>
                    <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($evaluation['macro_f1'], 3) }}</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">{{ __('Balanced precision/recall') }}</div>
                </div>
                <div class="p-3.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Severity Agreement') }}</div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ number_format($evaluation['severity_exact_match'] * 100, 1) }}%</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">{{ __('Exact 1-5 severity match') }}</div>
                </div>
                <div class="p-3.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Severity Error (MAE)') }}</div>
                    <div class="text-2xl font-bold text-slate-700 dark:text-slate-300 mt-1">{{ number_format($evaluation['severity_mae'], 2) }}</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">{{ __('Mean Absolute Error') }}</div>
                </div>
                <div class="p-3.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 col-span-2 sm:col-span-1">
                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Evaluated Reports') }}</div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ number_format($evaluation['total_moderated']) }}</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">{{ __('Ground truth decisions') }}</div>
                </div>
            </div>

            {{-- Analyzer Comparative Breakdown --}}
            <div class="border border-slate-200 dark:border-slate-800 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-800/30">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-3 flex items-center gap-1.5">
                    <span>⚖️</span> {{ __('Analyzer Comparison: Gemini AI vs. Local Lexicon') }}
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div class="p-3 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                        <div class="flex justify-between items-center font-bold text-sm text-slate-900 dark:text-white mb-1.5">
                            <span class="flex items-center gap-1.5"><span>⚡</span> Local Lexicon Analyzer</span>
                            <span class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ $evaluation['source_breakdown']['keyword']['count'] ?? 0 }} {{ __('reports') }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                            <div>
                                <span class="text-slate-500 dark:text-slate-400">{{ __('Category Accuracy:') }}</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 ms-1">{{ number_format(($evaluation['source_breakdown']['keyword']['accuracy'] ?? 0) * 100, 1) }}%</span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400">{{ __('Severity Match:') }}</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 ms-1">{{ number_format(($evaluation['source_breakdown']['keyword']['severity_exact'] ?? 0) * 100, 1) }}%</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                        <div class="flex justify-between items-center font-bold text-sm text-slate-900 dark:text-white mb-1.5">
                            <span class="flex items-center gap-1.5"><span>🧠</span> Gemini 2.5 Flash LLM</span>
                            <span class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ $evaluation['source_breakdown']['gemini']['count'] ?? 0 }} {{ __('reports') }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-slate-700">
                            <div>
                                <span class="text-slate-500 dark:text-slate-400">{{ __('Category Accuracy:') }}</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 ms-1">{{ number_format(($evaluation['source_breakdown']['gemini']['accuracy'] ?? 0) * 100, 1) }}%</span>
                            </div>
                            <div>
                                <span class="text-slate-500 dark:text-slate-400">{{ __('Severity Match:') }}</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 ms-1">{{ number_format(($evaluation['source_breakdown']['gemini']['severity_exact'] ?? 0) * 100, 1) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Confusion Matrix --}}
            @if (! empty($evaluation['confusion_matrix']))
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-2 flex items-center gap-1.5">
                        <span>🎯</span> {{ __('Confusion Matrix (Rows: Human Ground Truth vs Columns: AI Prediction)') }}
                    </h4>
                    <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-lg">
                        <table class="w-full text-xs text-start">
                            <thead class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                <tr>
                                    <th class="py-2 px-3 text-start border-b border-r dark:border-slate-700 font-bold">{{ __('Actual (Human)') }} \ {{ __('Predicted (AI)') }}</th>
                                    @foreach (\App\Models\Report::CATEGORIES as $pred)
                                        <th class="py-2 px-2 text-center border-b dark:border-slate-700 font-medium truncate" title="{{ \App\Models\Report::categoryLabel($pred) }}">
                                            {{ substr(\App\Models\Report::categoryLabel($pred), 0, 7) }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @foreach (\App\Models\Report::CATEGORIES as $actual)
                                    <tr>
                                        <td class="py-2 px-3 font-semibold border-r dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                            {{ \App\Models\Report::categoryLabel($actual) }}
                                        </td>
                                        @foreach (\App\Models\Report::CATEGORIES as $pred)
                                            @php
                                                $count = $evaluation['confusion_matrix'][$actual][$pred] ?? 0;
                                                $isDiagonal = ($actual === $pred);
                                                $cellCls = 'text-slate-400 dark:text-slate-600';
                                                if ($count > 0) {
                                                    $cellCls = $isDiagonal
                                                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 font-bold'
                                                        : 'bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300 font-bold';
                                                }
                                            @endphp
                                            <td class="py-2 px-2 text-center {{ $cellCls }}">
                                                {{ $count }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Per-Category Classification Report --}}
            @if (! empty($evaluation['category_metrics']))
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-2 flex items-center gap-1.5">
                        <span>📑</span> {{ __('Category Precision, Recall & F1-Score') }}
                    </h4>
                    <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-lg">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                <tr>
                                    <th class="py-2 px-3 text-start">{{ __('Category') }}</th>
                                    <th class="py-2 px-3 text-center">{{ __('Support (N)') }}</th>
                                    <th class="py-2 px-3 text-center">{{ __('Precision') }}</th>
                                    <th class="py-2 px-3 text-center">{{ __('Recall') }}</th>
                                    <th class="py-2 px-3 text-center">{{ __('F1-Score') }}</th>
                                    <th class="py-2 px-3 text-center">{{ __('Assessment') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @foreach ($evaluation['category_metrics'] as $cat => $m)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                        <td class="py-2 px-3 font-medium text-slate-800 dark:text-slate-200">{{ \App\Models\Report::categoryLabel($cat) }}</td>
                                        <td class="py-2 px-3 text-center text-slate-600 dark:text-slate-400">{{ $m['support'] }}</td>
                                        <td class="py-2 px-3 text-center font-mono text-slate-700 dark:text-slate-300">{{ number_format($m['precision'], 3) }}</td>
                                        <td class="py-2 px-3 text-center font-mono text-slate-700 dark:text-slate-300">{{ number_format($m['recall'], 3) }}</td>
                                        <td class="py-2 px-3 text-center font-mono font-bold {{ $m['f1'] >= 0.85 ? 'text-emerald-600 dark:text-emerald-400' : ($m['f1'] >= 0.65 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }}">
                                            {{ number_format($m['f1'], 3) }}
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            @if ($m['support'] === 0)
                                                <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ __('No samples') }}</span>
                                            @elseif ($m['f1'] >= 0.85)
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">✓ {{ __('Robust') }}</span>
                                            @elseif ($m['f1'] >= 0.65)
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 dark:text-amber-300">▲ {{ __('Moderate') }}</span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-700 dark:text-rose-300">! {{ __('Needs tuning') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- Quick actions --}}
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.reports') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg px-5 py-2.5 text-sm font-semibold transition">📋 {{ __('Moderate reports') }}</a>
            <a href="{{ route('admin.venues') }}" class="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 rounded-lg px-5 py-2.5 text-sm font-semibold transition">🏢 {{ __('Approve venues') }}</a>
            @hasrole('super_admin')
                <a href="{{ route('admin.users') }}" class="bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 rounded-lg px-5 py-2.5 text-sm font-semibold transition">👥 {{ __('Manage users') }}</a>
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
