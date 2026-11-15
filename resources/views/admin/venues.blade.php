<x-app-layout>
    <div class="max-w-3xl mx-auto p-4">
        <h1 class="text-xl font-bold mb-3">{{ __('Pending venues') }}</h1>
        @forelse ($venues as $v)
            <div class="bg-white rounded-xl shadow-sm p-4 mb-2 flex justify-between items-center gap-3">
                <div class="text-sm"><b>{{ $v->name }}</b> - {{ $v->area }} ({{ $v->category }})<div class="text-xs text-slate-500">{{ __('by') }} {{ $v->owner?->name ?? '-' }} · {{ $v->lat }}, {{ $v->lng }}</div></div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.venues.approve', $v) }}">@csrf <button class="bg-green-600 text-white px-3 py-1 rounded text-sm">{{ __('Approve') }}</button></form>
                    <form method="POST" action="{{ route('admin.venues.reject', $v) }}">@csrf <button class="bg-red-600 text-white px-3 py-1 rounded text-sm">{{ __('Reject') }}</button></form>
                </div>
            </div>
        @empty
            <p class="text-slate-600">{{ __('No pending venues.') }}</p>
        @endforelse
    </div>
</x-app-layout>
