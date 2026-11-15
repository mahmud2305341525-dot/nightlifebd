<x-app-layout>
    <div class="max-w-4xl mx-auto p-4 space-y-4">
        <div class="flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ __('My venues') }}</h1>
            <a href="{{ route('partner.venues.create') }}" class="bg-indigo-600 text-white rounded-md px-4 py-2 text-sm font-semibold">+ {{ __('Add venue') }}</a>
        </div>
        @forelse ($venues as $v)
            <div class="bg-white rounded-xl shadow-sm p-4 space-y-2">
                <div class="flex justify-between items-start gap-2">
                    <div>
                        <div class="font-semibold">{{ $v->name }} <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100">{{ __(ucfirst($v->status)) }}</span></div>
                        <div class="text-sm text-slate-500">{{ ucfirst($v->category) }} · {{ $v->area }}</div>
                    </div>
                    <a href="{{ route('partner.venues.edit', $v) }}" class="text-sm text-indigo-600 underline">{{ __('Edit') }}</a>
                </div>
                @if ($v->status === 'approved')
                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                        @foreach (\App\Support\TimeBucket::ALL as $b)
                            @php $s = $v->scoreFor($b); @endphp
                            <div class="rounded-lg border p-2"><div class="text-slate-500">{{ ucfirst($b) }}</div><div class="font-bold">{{ $s?->score ?? '-' }}</div><x-badge :badge="$s?->badge ?? 'unrated'" /></div>
                        @endforeach
                    </div>
                    <div class="rounded-lg bg-indigo-50 border border-indigo-100 p-3 text-sm">
                        <div class="flex justify-between items-center mb-1">
                            <b>🧠 {{ __('Venue Safety Coach (AI)') }}</b>
                            <form method="POST" action="{{ route('partner.venues.coach', $v) }}">@csrf <button class="text-xs underline text-indigo-700">{{ $v->coach_tips ? __('Refresh tips') : __('Get tips') }}</button></form>
                        </div>
                        @if ($v->coach_tips)
                            <ol class="list-decimal ms-5 space-y-1">@foreach ($v->coach_tips as $t)<li>{{ $t }}</li>@endforeach</ol>
                            <div class="text-xs text-slate-500 mt-1">{{ __('Updated') }} {{ $v->coach_at?->diffForHumans() }}</div>
                        @else
                            <p class="text-slate-600">{{ __('Get 3 specific improvements based on verified community reports about your venue.') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="text-slate-600">{{ __('You have no venues yet.') }}</p>
        @endforelse
    </div>
</x-app-layout>
