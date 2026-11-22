<x-app-layout>
    <div class="max-w-5xl mx-auto p-4 space-y-4">
        <h1 class="text-2xl font-bold">{{ __('Venue ownership claims') }}</h1>
        @forelse ($claims as $claim)
            <div class="border rounded-xl p-4 bg-white dark:bg-slate-900 space-y-2">
                <div class="font-semibold">{{ $claim->venue->name }} · {{ $claim->user->email }}</div>
                <div class="text-sm text-slate-600">Phone: {{ $claim->phone ?: __('Not provided') }} · Email: {{ $claim->email ?: __('Not provided') }}</div>
                @if ($claim->note)<p class="text-sm">{{ $claim->note }}</p>@endif
                @if ($claim->evidence_path)
                    <p class="text-xs">{{ __('Private evidence is stored securely and is available only to moderators.') }}</p>
                @endif
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.venue-claims.approve', $claim) }}">@csrf<button class="px-3 py-1.5 bg-emerald-600 text-white rounded">{{ __('Approve') }}</button></form>
                    <form method="POST" action="{{ route('admin.venue-claims.reject', $claim) }}">@csrf<button class="px-3 py-1.5 bg-red-600 text-white rounded">{{ __('Reject') }}</button></form>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ __('No pending venue claims.') }}</p>
        @endforelse
        {{ $claims->links() }}
    </div>
</x-app-layout>
