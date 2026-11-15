<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h2>
        <p class="text-sm text-slate-500">{{ \App\Support\TimeBucket::label($bucket) }}</p>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        @if ($contactCount === 0)
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">
                {{ __('Please configure at least one emergency contact so SOS, trips and the safety timer can reach someone.') }}
                <a href="{{ route('contacts.index') }}" class="underline font-semibold">{{ __('Add contacts') }}</a>
            </div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach ([
                ['reports.create', '📝', __('Report')],
                ['route.index', '🛣️', __('Safe Route')],
                ['planner.index', '🌙', __('Night Planner')],
                ['trips.index', '📍', __('Live trip')],
                ['timer.index', '⏱️', __('Safety timer')],
                ['fakecall', '📞', __('Fake call')],
                ['near-me', '🏥', __('Near Me')],
                ['heatmap.index', '🔥', __('Heatmap')],
            ] as [$r, $icon, $label])
                <a href="{{ route($r) }}" class="bg-white rounded-xl shadow-sm p-4 text-center hover:shadow-md transition">
                    <div class="text-3xl">{{ $icon }}</div>
                    <div class="mt-1 text-sm font-semibold">{{ $label }}</div>
                </a>
            @endforeach
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold mb-2">{{ __('Active safety tools') }}</h3>
                @if ($activeTrip)
                    <p class="text-sm">📍 {{ __('Live trip running until :t', ['t' => $activeTrip->expires_at->format('h:i A')]) }} -
                        <a class="text-indigo-600 underline" href="{{ route('trips.show', $activeTrip->token) }}">{{ __('open') }}</a></p>
                @endif
                @if ($timer)
                    <p class="text-sm mt-1">⏱️ {{ __('Safety timer due :t', ['t' => $timer->due_at->format('h:i A')]) }} -
                        <a class="text-indigo-600 underline" href="{{ route('timer.index') }}">{{ __('open') }}</a></p>
                @endif
                @if (! $activeTrip && ! $timer)
                    <p class="text-sm text-slate-500">{{ __('Nothing running. Start a live trip or a safety timer before you head out.') }}</p>
                @endif
            </div>

            <div class="bg-white rounded-xl shadow-sm p-4">
                <h3 class="font-semibold mb-2">{{ __('Venues to avoid right now') }}</h3>
                @forelse ($cautionVenues as $v)
                    <a href="{{ route('venues.show', $v) }}" class="flex justify-between text-sm py-1 hover:underline"><span>{{ $v->name }} <span class="text-slate-400">· {{ $v->area }}</span></span><x-badge badge="caution" /></a>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No venue has a caution rating at this hour.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex justify-between items-center mb-2">
                <h3 class="font-semibold">{{ __('My latest reports') }}</h3>
                <a href="{{ route('reports.mine') }}" class="text-sm text-indigo-600 underline">{{ __('All reports') }}</a>
            </div>
            @forelse ($reports as $r)
                <div class="text-sm py-1 border-b last:border-0 flex justify-between gap-3">
                    <span class="truncate">{{ \Illuminate\Support\Str::limit($r->body, 80) }}</span>
                    <span class="shrink-0 text-xs px-2 py-0.5 rounded-full {{ ['pending' => 'bg-amber-100 text-amber-800', 'verified' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$r->status] }}">{{ __(ucfirst($r->status)) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('You have not submitted any report yet.') }}</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
