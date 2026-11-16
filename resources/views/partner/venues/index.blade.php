<x-app-layout>
    <div class="max-w-4xl mx-auto p-4 space-y-4">
        <div class="flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ __('My venues') }}</h1>
            <a href="{{ route('partner.venues.create') }}" class="bg-indigo-600 text-white rounded-md px-4 py-2 text-sm font-semibold">+ {{ __('Add venue') }}</a>
        </div>
        @forelse ($venues as $v)
            <div class="bg-white rounded-xl shadow-sm p-4 space-y-2">
                <div class="flex justify-between items-start gap-2">
                    <div>
                        <div class="font-semibold">{{ $v->name }} <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100">{{ __(ucfirst($v->status)) }}</span></div>
                        <div class="text-sm text-slate-500">{{ ucfirst($v->category) }} · {{ $v->area }}</div>
                    </div>
                    <a href="{{ route('partner.venues.edit', $v) }}" class="text-sm text-indigo-600 underline">{{ __('Edit') }}</a>
                </div>
                @if ($v->status === 'approved')
                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                        @foreach (\App\Support\TimeBucket::ALL as $b)
                            @php $s = $v->scoreFor($b); @endphp
                            <div class="rounded-lg border p-2"><div class="text-slate-500">{{ ucfirst($b) }}</div><div class="font-bold">{{ $s?->score ?? '-' }}</div><x-badge :badge="$s?->badge ?? 'unrated'" /></div>
                        @endforeach
                    </div>
                    <div class="rounded-xl bg-indigo-50/70 border border-indigo-100 p-4 text-sm space-y-3">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <span class="text-base">🧠</span>
                                <span class="font-bold text-indigo-950">{{ __('Venue Safety Coach (AI & ROI Analysis)') }}</span>
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
                                        <div class="bg-white rounded-lg p-3 border border-indigo-100 shadow-sm space-y-1.5">
                                            <div class="flex flex-wrap items-center justify-between gap-1">
                                                <span class="font-bold text-slate-800 text-xs">{{ $t['title'] ?? '' }}</span>
                                                <div class="flex items-center gap-1.5 text-[10px] font-semibold">
                                                    @if (!empty($t['impact']))
                                                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">📈 {{ $t['impact'] }}</span>
                                                    @endif
                                                    @if (!empty($t['cost']))
                                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">💰 {{ $t['cost'] }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <p class="text-xs text-slate-600 leading-relaxed">{{ $t['detail'] ?? '' }}</p>
                                        </div>
                                    @else
                                        <div class="bg-white rounded-lg p-2.5 border border-indigo-100 text-xs text-slate-700">
                                            • {{ $t }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            <div class="text-[11px] text-slate-400 text-right">{{ __('Analyzed') }} {{ $v->coach_at?->diffForHumans() }}</div>
                        @else
                            <p class="text-xs text-slate-600">{{ __('Run AI safety analysis to get 3 concrete, ROI-scored improvements based on verified community reports.') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="text-slate-600">{{ __('You have no venues yet.') }}</p>
        @endforelse
    </div>
</x-app-layout>
