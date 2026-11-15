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
                @if ($status === 'pending')
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.reports.verify', $r) }}">@csrf <button class="bg-green-600 text-white px-3 py-1 rounded">{{ __('Verify') }}</button></form>
                        <form method="POST" action="{{ route('admin.reports.reject', $r) }}">@csrf <button class="bg-red-600 text-white px-3 py-1 rounded">{{ __('Reject') }}</button></form>
                        <form method="POST" action="{{ route('admin.reports.analyze', $r) }}">@csrf <button class="border px-3 py-1 rounded">🧠 {{ $r->category ? __('Re-run AI') : __('Analyse with AI') }}</button></form>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-slate-600">{{ __('Nothing here.') }}</p>
        @endforelse
        {{ $reports->links() }}
    </div>
</x-app-layout>
