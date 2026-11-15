<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">{{ __('My reports') }}</h2></x-slot>
    <div class="max-w-4xl mx-auto p-4 space-y-3">
        @forelse ($reports as $r)
            <div class="bg-white rounded-xl shadow-sm p-4 text-sm">
                <div class="flex justify-between gap-2">
                    <span class="font-semibold">{{ $r->venue?->name ?? __('Map location') }} <span class="text-slate-400 font-normal">· {{ $r->incident_time->format('d M, h:i A') }}</span></span>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ ['pending' => 'bg-amber-100 text-amber-800', 'verified' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$r->status] }}">{{ __(ucfirst($r->status)) }}</span>
                </div>
                <p class="mt-1 text-slate-700">{{ $r->body }}</p>
                <div class="mt-2 text-xs text-slate-500 flex flex-wrap gap-x-4">
                    @if ($r->category)
                        <span>🧠 {{ \App\Models\Report::categoryLabel($r->category) }} · {{ __('severity') }} {{ $r->severity }}/5 · {{ $r->analysis_source }}</span>
                        @if ($r->duplicate_of)<span>🔁 {{ __('Matches an earlier report (counted as corroboration)') }}</span>@endif
                    @else
                        <span>⏳ {{ __('Waiting for AI analysis') }}</span>
                    @endif
                </div>
                @if ($r->summary_en)<p class="text-xs text-slate-500 mt-1">AI: {{ $r->summary_en }}</p>@endif
            </div>
        @empty
            <p class="text-slate-600">{{ __('You have not submitted any report yet.') }} <a class="underline text-indigo-600" href="{{ route('reports.create') }}">{{ __('Write one') }}</a></p>
        @endforelse
        {{ $reports->links() }}
    </div>
</x-app-layout>
