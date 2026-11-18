<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl leading-tight text-slate-800 dark:text-slate-100">{{ __('Venue Right-of-Reply') }}</h2>
            <a href="{{ route('partner.venues.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← {{ __('Back to My Venues') }}</a>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto p-4 space-y-4">
        <div class="bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 rounded-xl p-4 text-xs text-indigo-900 dark:text-indigo-200">
            <p class="font-semibold mb-1">📢 {{ __('Official Venue Right-of-Reply System') }}</p>
            <p>{{ __('As a verified venue partner, you have the right to respond to community reports concerning your venues. Approved responses will appear publicly beside the report as official statements. Community reports cannot be altered or removed.') }}</p>
        </div>

        @if (session('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm p-3 rounded-lg">
                {{ session('status') }}
            </div>
        @endif

        @forelse ($reports as $r)
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm p-5 space-y-3 border border-slate-200 dark:border-slate-800">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="font-bold text-slate-900 dark:text-slate-100">{{ $r->venue->name }}</span>
                        <span class="text-slate-400 dark:text-slate-500 text-xs ml-2">· {{ $r->incident_time->format('d M Y, h:i A') }}</span>
                    </div>
                    <span class="px-2 py-0.5 rounded text-xs font-medium {{ $r->status === 'verified' ? 'bg-green-100 dark:bg-green-950/60 text-green-800 dark:text-green-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">
                        {{ ucfirst($r->status) }}
                    </span>
                </div>

                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 text-sm text-slate-700 dark:text-slate-300">
                    <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                        {{ \App\Models\Report::categoryLabel($r->category) }}
                        @if ($r->severity) · {{ __('Severity') }}: {{ $r->severity }}/5 @endif
                    </div>
                    <p>{{ $r->body }}</p>
                </div>

                {{-- Existing Responses --}}
                @if ($r->responses->isNotEmpty())
                    <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Your Venue Statements:') }}</div>
                        @foreach ($r->responses as $resp)
                            <div class="p-3 rounded-lg bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60 text-xs text-slate-700 dark:text-slate-300">
                                <div class="flex justify-between items-center text-indigo-900 dark:text-indigo-200 font-semibold mb-1">
                                    <span>🏢 {{ __('Official Statement') }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] {{ $resp->status === 'approved' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300' : ($resp->status === 'rejected' ? 'bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300') }}">
                                        {{ ucfirst($resp->status) }}
                                    </span>
                                </div>
                                <p>{{ $resp->response }}</p>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">{{ $resp->created_at->diffForHumans() }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Post a Response Form --}}
                <div class="pt-2 border-t border-slate-100 dark:border-slate-800" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                        💬 <span x-text="open ? '{{ __('Cancel') }}' : '{{ __('Post Official Statement') }}'"></span>
                    </button>

                    <form x-show="open" method="POST" action="{{ route('partner.responses.store', $r) }}" class="mt-3 space-y-2">
                        @csrf
                        <textarea name="response" rows="3" required minlength="10" maxlength="1000"
                            class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="{{ __('Write an official, polite response addressing this report (10-1000 characters)…') }}"></textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="open = false" class="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200">{{ __('Cancel') }}</button>
                            <button type="submit" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow-sm transition">
                                {{ __('Submit Statement') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-6 text-center text-slate-500 dark:text-slate-400 text-sm">
                {{ __('No reports have been submitted for your venues yet.') }}
            </div>
        @endforelse

        {{ $reports->links() }}
    </div>
</x-app-layout>
