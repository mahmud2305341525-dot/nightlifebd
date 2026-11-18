<x-app-layout>
    <div class="max-w-4xl mx-auto p-4 space-y-4">
        <div class="flex justify-between items-center">
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ __('My venues') }}</h1>
            <div class="flex items-center gap-2">
                <a href="{{ route('partner.responses.index') }}" class="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-md px-3 py-2 text-sm font-semibold transition">💬 {{ __('Right-of-Reply') }}</a>
                <a href="{{ route('partner.venues.create') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white rounded-md px-4 py-2 text-sm font-semibold transition">+ {{ __('Add venue') }}</a>
            </div>
        </div>
        @forelse ($venues as $v)
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 space-y-2">
                <div class="flex justify-between items-start gap-2">
                    <div>
                        <div class="font-semibold text-slate-900 dark:text-slate-100">{{ $v->name }} <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">{{ __(ucfirst($v->status)) }}</span></div>
                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ ucfirst($v->category) }} · {{ $v->area }}</div>
                    </div>
                    <a href="{{ route('partner.venues.edit', $v) }}" class="text-sm text-indigo-600 dark:text-indigo-400 underline">{{ __('Edit') }}</a>
                </div>
                @if ($v->status === 'approved')
                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                        @foreach (\App\Support\TimeBucket::ALL as $b)
                            @php $s = $v->scoreFor($b); @endphp
                            <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-2"><div class="text-slate-500 dark:text-slate-400">{{ ucfirst($b) }}</div><div class="font-bold text-slate-900 dark:text-slate-100">{{ $s?->score ?? '-' }}</div><x-badge :badge="$s?->badge ?? 'unrated'" /></div>
                        @endforeach
                    </div>
                    <div class="rounded-xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-800 p-4 text-sm space-y-3">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <span class="text-base">🧠</span>
                                <span class="font-bold text-indigo-950 dark:text-indigo-200">{{ __('Venue Safety Coach (AI & ROI Analysis)') }}</span>
                            </div>
                            <form method="POST" action="{{ route('partner.venues.coach', $v) }}">
                                @csrf
                                <button type="submit" class="text-xs bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-2.5 py-1 rounded-md shadow-sm transition">
                                    {{ $v->coach_tips ? __('Refresh AI Tips') : __('Analyze & Get Tips') }}
                                </button>
                            </form>
                        </div>

                        @if ($v->coach_tips)
                            <div class="space-y-2">
                                @foreach ($v->coach_tips as $t)
                                    @if (is_array($t))
                                        <div class="bg-white dark:bg-slate-900 rounded-lg p-3 border border-indigo-100 dark:border-slate-700 shadow-sm space-y-1.5">
                                            <div class="flex flex-wrap items-center justify-between gap-1">
                                                <span class="font-bold text-slate-800 dark:text-slate-100 text-xs">{{ $t['title'] ?? '' }}</span>
                                                <div class="flex items-center gap-1.5 text-[10px] font-semibold">
                                                    @if (!empty($t['impact']))
                                                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">📈 {{ $t['impact'] }}</span>
                                                    @endif
                                                    @if (!empty($t['cost']))
                                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">💰 {{ $t['cost'] }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ $t['detail'] ?? '' }}</p>
                                        </div>
                                    @else
                                        <div class="bg-white dark:bg-slate-900 rounded-lg p-2.5 border border-indigo-100 dark:border-slate-700 text-xs text-slate-700 dark:text-slate-300">
                                            • {{ $t }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <div class="text-[11px] text-slate-400 dark:text-slate-500 text-right">{{ __('Analyzed') }} {{ $v->coach_at?->diffForHumans() }}</div>
                        @else
                            <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('Run AI safety analysis to get 3 concrete, ROI-scored improvements based on verified community reports.') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="text-slate-600 dark:text-slate-400">{{ __('You have no venues yet.') }}</p>
        @endforelse
    </div>
</x-app-layout>
