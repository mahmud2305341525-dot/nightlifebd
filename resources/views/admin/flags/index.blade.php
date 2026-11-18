<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl leading-tight text-slate-800 dark:text-slate-100">{{ __('Community Abuse & Flag Moderation') }}</h2>
            <div class="flex gap-2">
                <a href="{{ route('admin.flags.index', ['status' => 'pending']) }}" class="px-3 py-1 rounded text-xs font-semibold {{ $status === 'pending' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">{{ __('Pending') }}</a>
                <a href="{{ route('admin.flags.index', ['status' => 'all']) }}" class="px-3 py-1 rounded text-xs font-semibold {{ $status === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">{{ __('All') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto p-4 space-y-4">
        @if (session('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm p-3 rounded-lg">
                {{ session('status') }}
            </div>
        @endif

        @forelse ($flags as $flag)
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm p-5 space-y-3 border border-slate-200 dark:border-slate-800">
                <div class="flex flex-wrap justify-between items-start gap-2">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300">
                                🚩 {{ \App\Models\ReportFlag::REASONS[$flag->reason] ?? ucfirst($flag->reason) }}
                            </span>
                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                Flagged by {{ $flag->user->name }} · {{ $flag->created_at->diffForHumans() }}
                            </span>
                        </div>
                        @if ($flag->details)
                            <p class="text-xs text-slate-600 dark:text-slate-300 italic">"{{ $flag->details }}"</p>
                        @endif
                    </div>

                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $flag->status === 'pending' ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300' : ($flag->status === 'resolved' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400') }}">
                        {{ ucfirst($flag->status) }}
                    </span>
                </div>

                {{-- The Reported Content --}}
                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs space-y-1.5">
                    <div class="flex justify-between font-semibold text-slate-700 dark:text-slate-300">
                        <span>Report #{{ $flag->report->id }} · {{ $flag->report->venue?->name ?? 'Map location' }} · Author: {{ $flag->report->user?->name ?? 'Anonymous' }}</span>
                        <span class="text-slate-400 dark:text-slate-500">{{ $flag->report->incident_time?->format('d M Y, h:i A') }}</span>
                    </div>
                    <p class="text-slate-800 dark:text-slate-200">{{ $flag->report->body }}</p>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                        Status: <span class="font-semibold">{{ ucfirst($flag->report->status) }}</span> · Category: {{ $flag->report->category ?? '-' }} · Severity: {{ $flag->report->severity ?? '-' }}/5
                    </div>
                </div>

                {{-- Moderation Actions --}}
                @if ($flag->status === 'pending')
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <form method="POST" action="{{ route('admin.flags.dismiss', $flag) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 rounded-md text-xs font-semibold border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                ✕ {{ __('Dismiss Flag (Keep Report)') }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.flags.resolve', $flag) }}" onsubmit="return confirm('{{ __('Reject this report and recalculate affected venue scores?') }}')">
                            @csrf
                            <input type="hidden" name="action" value="reject_report">
                            <button type="submit" class="px-3 py-1.5 rounded-md text-xs font-semibold bg-red-600 hover:bg-red-500 text-white transition shadow-sm">
                                ⛔ {{ __('Reject & Remove Report') }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.flags.resolve', $flag) }}" onsubmit="return confirm('{{ __('Ban this user and reject all their reports?') }}')">
                            @csrf
                            <input type="hidden" name="action" value="ban_author">
                            <button type="submit" class="px-3 py-1.5 rounded-md text-xs font-semibold bg-slate-900 hover:bg-black dark:bg-slate-800 dark:hover:bg-slate-700 text-white transition shadow-sm">
                                🚫 {{ __('Ban Author & Reject') }}
                            </button>
                        </form>
                    </div>
                @else
                    <div class="text-xs text-slate-500 dark:text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800 flex justify-between">
                        <span>Action: {{ $flag->action_taken ?? 'Resolved' }}</span>
                        <span>Reviewed by: {{ $flag->reviewer?->name ?? 'Moderator' }}</span>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-6 text-center text-slate-500 dark:text-slate-400 text-sm">
                {{ __('No report flags found.') }}
            </div>
        @endforelse

        {{ $flags->links() }}
    </div>
</x-app-layout>
