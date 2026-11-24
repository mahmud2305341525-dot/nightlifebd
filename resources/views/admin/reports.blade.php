<x-app-layout>
    <div class="max-w-5xl mx-auto p-4 space-y-3">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ __('Report moderation (AI pre-triaged, highest severity first)') }}</h1>
            <div class="flex gap-1 text-sm">
                @foreach (['pending', 'verified', 'rejected'] as $s)
                    <a href="{{ route('admin.reports', ['status' => $s]) }}" class="px-3 py-1 rounded-full {{ $status === $s ? 'bg-slate-900 dark:bg-indigo-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700' }}">{{ __(ucfirst($s)) }}</a>
                @endforeach
            </div>
        </div>

        @forelse ($reports as $r)
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 text-sm space-y-2">
                <div class="flex justify-between gap-2 flex-wrap">
                    <div class="font-semibold text-slate-800 dark:text-slate-100">
                        #{{ $r->id }} · {{ $r->venue?->name ?? __('Map location') }}
                        <span class="text-slate-400 dark:text-slate-500 font-normal">· {{ $r->incident_time->format('d M h:i A') }} · {{ $r->user->name }} ({{ __('trust') }} {{ number_format($r->user->trust_score, 2) }})</span>
                    </div>
                    <div class="flex flex-wrap gap-1 text-xs">
                        @if ($r->rating)
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300">⭐ {{ $r->rating }}/5 {{ __('review') }}</span>
                        @endif
                        @if ($r->category)
                            <span class="px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300">{{ \App\Models\Report::categoryLabel($r->category) }}</span>
                            <span class="px-2 py-0.5 rounded-full {{ $r->severity >= 4 ? 'bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">{{ __('severity') }} {{ $r->severity }}/5</span>
                            <span class="px-2 py-0.5 rounded-full {{ $r->spam_probability >= 0.6 ? 'bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">spam {{ number_format($r->spam_probability * 100) }}%</span>
                            <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">{{ $r->language }} · {{ $r->analysis_source }}</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300">{{ __('not analysed yet') }}</span>
                        @endif
                        @if ($r->needs_review)<span class="px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300">{{ __('needs careful review') }}</span>@endif
                        @if ($r->duplicate_of)<span class="px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-950/60 text-purple-800 dark:text-purple-300">{{ __('duplicate of') }} #{{ $r->duplicate_of }}</span>@endif
                        @if ($r->duplicates_count)<span class="px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-950/60 text-purple-800 dark:text-purple-300">{{ $r->duplicates_count }} {{ __('duplicate(s)') }}</span>@endif
                    </div>
                </div>
                <p class="text-slate-800 dark:text-slate-200">{{ $r->body }}</p>
                @if ($r->summary_en)<p class="text-xs text-slate-500 dark:text-slate-400">AI summary: {{ $r->summary_en }}</p>@endif
                @if ($r->ai_suggested_category)
                    <div class="text-xs rounded-lg border border-indigo-200 bg-indigo-50 dark:bg-indigo-950/40 p-2">
                        <strong>{{ __('Moderator copilot') }}:</strong>
                        {{ \App\Models\Report::categoryLabel($r->ai_suggested_category) }},
                        {{ __('severity') }} {{ $r->ai_suggested_severity }}/5.
                        {{ $r->ai_suggested_rationale }}
                        @if ($r->defamation_flagged)<span class="text-red-700 font-semibold">{{ __('Defamation/name-risk flag') }}</span>@endif
                        @if ($r->pii_scrubbed)<span class="text-amber-700 font-semibold">{{ __('PII scrubbed from summary') }}</span>@endif
                    </div>
                @endif

                {{-- Photo evidence thumbnails --}}
                @if ($r->photos && $r->photos->isNotEmpty())
                    <div class="flex gap-2 flex-wrap">
                        @foreach ($r->photos as $photo)
                            <a href="{{ $photo->url() }}" target="_blank" class="block" title="{{ __('View full photo') }}">
                                <img src="{{ $photo->thumbUrl() }}" alt="{{ __('Evidence photo') }}" class="w-20 h-20 object-cover rounded-lg border-2 border-slate-200 dark:border-slate-700 hover:border-indigo-400 transition shadow-sm">
                            </a>
                            @if ($photo->ai_advice)
                                <span class="text-[10px] text-slate-500 self-end max-w-44">
                                    {{ ucfirst($photo->ai_relevance ?? 'unknown') }} · {{ $photo->ai_advice }}
                                    @if ($photo->ai_faces_blur_suggested) · {{ __('Blur faces/plates suggested') }}@endif
                                    @if ($photo->ai_explicit_flagged) · {{ __('Explicit content flag') }}@endif
                                </span>
                            @endif
                        @endforeach
                        <span class="text-xs text-slate-400 dark:text-slate-500 self-end">📸 {{ $r->photos->count() }} {{ __('photo(s)') }}</span>
                    </div>
                @endif

                @if ($status === 'pending')
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-lg">
                        <form method="POST" action="{{ route('admin.reports.verify', $r) }}" class="flex flex-wrap items-center gap-2 text-xs">
                            @csrf
                            <label class="font-semibold text-slate-700 dark:text-slate-300">{{ __('Category:') }}</label>
                            <select name="category" class="text-xs py-1 px-2 border-slate-300 dark:border-slate-700 rounded bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                                @foreach (\App\Models\Report::CATEGORIES as $cat)
                                    <option value="{{ $cat }}" @selected($r->category === $cat)>{{ \App\Models\Report::categoryLabel($cat) }}</option>
                                @endforeach
                            </select>

                            <label class="font-semibold text-slate-700 dark:text-slate-300 ml-1">{{ __('Severity:') }}</label>
                            <select name="severity" class="text-xs py-1 px-2 border-slate-300 dark:border-slate-700 rounded bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                                @foreach ([1, 2, 3, 4, 5] as $sev)
                                    <option value="{{ $sev }}" @selected((int)$r->severity === $sev)>{{ $sev }}/5</option>
                                @endforeach
                            </select>

                            <button type="submit" class="bg-green-600 hover:bg-green-500 text-white font-bold px-3 py-1 rounded transition shadow-sm">
                                ✔ {{ __('Verify & Apply') }}
                            </button>
                        </form>

                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.reports.reject', $r) }}">
                                @csrf
                                <button type="submit" class="bg-red-600 hover:bg-red-500 text-white font-bold px-3 py-1 rounded text-xs transition shadow-sm">
                                    ✖ {{ __('Reject') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.reports.analyze', $r) }}">
                                @csrf
                                <button type="submit" class="border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 px-3 py-1 rounded text-xs transition">
                                    🧠 {{ $r->category ? __('Re-run AI') : __('Analyse with AI') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-slate-600 dark:text-slate-400">{{ __('Nothing here.') }}</p>
        @endforelse
        {{ $reports->links() }}
    </div>
</x-app-layout>
