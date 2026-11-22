<x-app-layout>
    <div class="max-w-5xl mx-auto p-4 space-y-4">
        <h1 class="text-2xl font-bold">{{ __('Evidence Assistant') }}</h1>
        <p class="text-sm text-slate-600">{{ __('Paste public notice text or a public source URL. Extracted candidates remain unverified and never automatically affect safety scores.') }}</p>
        <form method="POST" action="{{ route('admin.evidence-assistant.store') }}" class="border rounded-xl p-4 space-y-2">
            @csrf
            <input type="url" name="source_url" maxlength="255" placeholder="https://official-source.example/notice" class="w-full rounded">
            <textarea name="text" required maxlength="5000" rows="6" placeholder="{{ __('Paste only the facts or a short public excerpt') }}" class="w-full rounded"></textarea>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded">{{ __('Extract candidate') }}</button>
        </form>
        @foreach ($candidates as $candidate)
            <div class="border rounded-xl p-4 bg-white dark:bg-slate-900 space-y-2">
                <div class="text-xs text-slate-500">{{ ucfirst($candidate->status) }} · {{ $candidate->category }} · {{ $candidate->source_url ?: __('No source URL') }}</div>
                <div class="font-semibold">{{ $candidate->approximate_place ?: __('Place not identified') }}</div>
                <p class="text-sm">{{ $candidate->facts }}</p>
                @if ($candidate->status === 'unverified')
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.evidence-assistant.approve', $candidate) }}">@csrf<button class="px-3 py-1 bg-emerald-600 text-white rounded">{{ __('Approve') }}</button></form>
                        <form method="POST" action="{{ route('admin.evidence-assistant.reject', $candidate) }}">@csrf<button class="px-3 py-1 bg-red-600 text-white rounded">{{ __('Reject') }}</button></form>
                    </div>
                @endif
            </div>
        @endforeach
        {{ $candidates->links() }}
    </div>
</x-app-layout>
