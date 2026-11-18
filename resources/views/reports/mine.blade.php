<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100">{{ __('My reports') }}</h2></x-slot>
    <div class="max-w-4xl mx-auto p-4 space-y-3">
        @if (session('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm p-3 rounded-lg">
                {{ session('status') }}
            </div>
        @endif

        @forelse ($reports as $r)
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 text-sm">
                <div class="flex justify-between gap-2">
                    <span class="font-semibold text-slate-800 dark:text-slate-100">
                        {{ $r->venue?->name ?? __('Map location') }}
                        <span class="text-slate-400 dark:text-slate-500 font-normal">· {{ $r->incident_time->format('d M, h:i A') }}</span>
                        @if ($r->rating)
                            <span class="text-amber-400 ml-1">@for ($i = 1; $i <= 5; $i++)<span class="{{ $i <= $r->rating ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600' }}">★</span>@endfor</span>
                        @endif
                    </span>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ ['pending' => 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300', 'verified' => 'bg-green-100 dark:bg-green-950/60 text-green-800 dark:text-green-300', 'rejected' => 'bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300'][$r->status] }}">{{ __(ucfirst($r->status)) }}</span>
                </div>
                <p class="mt-1 text-slate-700 dark:text-slate-300">{{ $r->body }}</p>

                {{-- Photo thumbnails --}}
                @if ($r->photos->isNotEmpty())
                    <div class="flex gap-2 mt-2">
                        @foreach ($r->photos as $photo)
                            <a href="{{ $photo->url() }}" target="_blank" class="block">
                                <img src="{{ $photo->thumbUrl() }}" alt="{{ __('Evidence photo') }}" class="w-14 h-14 object-cover rounded-md border border-slate-200 dark:border-slate-700 hover:border-indigo-400 transition">
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="mt-2 text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap gap-x-4">
                        @if ($r->category)
                            <span>🧠 {{ \App\Models\Report::categoryLabel($r->category) }} · {{ __('severity') }} {{ $r->severity }}/5 · {{ $r->analysis_source }}</span>
                            @if ($r->duplicate_of)<span>🔁 {{ __('Matches an earlier report (counted as corroboration)') }}</span>@endif
                        @else
                            <span>⏳ {{ __('Waiting for AI analysis') }}</span>
                        @endif
                    </div>

                    @if ($r->status === 'pending')
                        <div class="flex items-center gap-2">
                            <a href="{{ route('reports.edit', $r) }}" class="px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold transition">
                                ✏️ {{ __('Edit') }}
                            </a>
                            <form method="POST" action="{{ route('reports.destroy', $r) }}" onsubmit="return confirm('{{ __('Are you sure you want to withdraw this report? This action cannot be undone.') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2.5 py-1 rounded bg-red-50 dark:bg-red-950/40 hover:bg-red-100 dark:hover:bg-red-900/60 text-red-600 dark:text-red-400 font-semibold transition">
                                    🗑️ {{ __('Withdraw') }}
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
                @if ($r->summary_en)<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">AI: {{ $r->summary_en }}</p>@endif
            </div>
        @empty
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm p-8 text-center border border-slate-200 dark:border-slate-800 space-y-3">
                <div class="text-4xl">📝</div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100 text-sm">{{ __('You have not submitted any reports yet') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                    {{ __('Community reports help keep Dhaka safe. Report a dark alley, harassment incident, or leave a venue safety review to empower others.') }}
                </p>
                <a href="{{ route('reports.create') }}" class="inline-block px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow-sm transition">
                    + {{ __('Write Your First Report') }}
                </a>
            </div>
        @endforelse
        {{ $reports->links() }}
    </div>
</x-app-layout>
