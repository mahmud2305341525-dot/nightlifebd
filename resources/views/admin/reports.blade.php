<x-app-layout>
    <div class="max-w-5xl mx-auto p-4 space-y-3">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <h1 class="text-xl font-bold">{{ __('Report moderation (AI pre-triaged, highest severity first)') }}</h1>
            <div class="flex gap-1 text-sm">
                @foreach (['pending', 'verified', 'rejected'] as $s)
                    <a href="{{ route('admin.reports', ['status' => $s]) }}" class="px-3 py-1 rounded-full {{ $status === $s ? 'bg-slate-900 text-white' : 'bg-white border' }}">{{ __(ucfirst($s)) }}</a>
                @endforeach
            </div>
        </div>

        @forelse ($reports as $r)
            <div class="bg-white rounded-xl shadow-sm p-4 text-sm space-y-2">
                <div class="flex justify-between gap-2 flex-wrap">
                    <div class="font-semibold">
                        #{{ $r->id }} · {{ $r->venue?->name ?? __('Map location') }}
                        <span class="text-slate-400 font-normal">· {{ $r->incident_time->format('d M h:i A') }} · {{ $r->user->name }} ({{ __('trust') }} {{ number_format($r->user->trust_score, 2) }})</span>
                    </div>
                    <div class="flex flex-wrap gap-1 text-xs">
                        @if ($r->rating)
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">⭐ {{ $r->rating }}/5 {{ __('review') }}</span>
                        @endif
                        @if ($r->category)
                            <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">{{ \App\Models\Report::categoryLabel($r->category) }}</span>
                            <span class="px-2 py-0.5 rounded-full {{ $r->severity >= 4 ? 'bg-red-100 text-red-800' : 'bg-slate-100' }}">{{ __('severity') }} {{ $r->severity }}/5</span>
                            <span class="px-2 py-0.5 rounded-full {{ $r->spam_probability >= 0.6 ? 'bg-red-100 text-red-800' : 'bg-slate-100' }}">spam {{ number_format($r->spam_probability * 100) }}%</span>
                            <span class="px-2 py-0.5 rounded-full bg-slate-100">{{ $r->language }} · {{ $r->analysis_source }}</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">{{ __('not analysed yet') }}</span>
                        @endif
                        @if ($r->needs_review)<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">{{ __('needs careful review') }}</span>@endif
                        @if ($r->duplicate_of)<span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-800">{{ __('duplicate of') }} #{{ $r->duplicate_of }}</span>@endif
                        @if ($r->duplicates_count)<span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-800">{{ $r->duplicates_count }} {{ __('duplicate(s)') }}</span>@endif
                    </div>
                </div>
                <p class="text-slate-800">{{ $r->body }}</p>
                @if ($r->summary_en)<p class="text-xs text-slate-500">AI summary: {{ $r->summary_en }}</p>@endif

                {{-- Photo evidence thumbnails --}}
                @if ($r->photos && $r->photos->isNotEmpty())
                    <div class="flex gap-2 flex-wrap">
                        @foreach ($r->photos as $photo)
                            <a href="{{ $photo->url() }}" target="_blank" class="block" title="{{ __('View full photo') }}">
                                <img src="{{ $photo->thumbUrl() }}" alt="{{ __('Evidence photo') }}" class="w-20 h-20 object-cover rounded-lg border-2 border-slate-200 hover:border-indigo-400 transition shadow-sm">
                            </a>
                        @endforeach
                        <span class="text-xs text-slate-400 self-end">📸 {{ $r->photos->count() }} {{ __('photo(s)') }}</span>
                    </div>
                @endif

                @if ($status === 'pending')
                    <div class="mt-3 pt-3 border-t flex flex-wrap items-center justify-between gap-3 bg-slate-50 p-2.5 rounded-lg">
                        <form method="POST" action="{{ route('admin.reports.verify', $r) }}" class="flex flex-wrap items-center gap-2 text-xs">
                            @csrf
                            <label class="font-semibold text-slate-700">{{ __('Category:') }}</label>
                            <select name="category" class="text-xs py-1 px-2 border-slate-300 rounded bg-white">
                                @foreach (\App\Models\Report::CATEGORIES as $cat)
                                    <option value="{{ $cat }}" @selected($r->category === $cat)>{{ \App\Models\Report::categoryLabel($cat) }}</option>
                                @endforeach
                            </select>

                            <label class="font-semibold text-slate-700 ml-1">{{ __('Severity:') }}</label>
                            <select name="severity" class="text-xs py-1 px-2 border-slate-300 rounded bg-white">
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
                                <button type="submit" class="border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 px-3 py-1 rounded text-xs transition">
                                    🧠 {{ $r->category ? __('Re-run AI') : __('Analyse with AI') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-slate-600">{{ __('Nothing here.') }}</p>
        @endforelse
        {{ $reports->links() }}
    </div>
</x-app-layout>

