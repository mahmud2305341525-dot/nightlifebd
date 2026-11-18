<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span>🔔</span>
                    <span>{{ __('Concert & Event Alerts') }}</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Notifications about upcoming live music, concerts, and night festivals near your area.') }}
                </p>
            </div>
            <a href="{{ route('events.index') }}" class="px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                {{ __('Browse Events') }}
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-4">

        @if ($notifications->count() > 0)
            <div class="space-y-3">
                @foreach ($notifications as $notif)
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="text-2xl p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 shrink-0">🎸</span>
                            <div>
                                <h4 class="font-bold text-sm text-slate-900 dark:text-slate-100">
                                    {{ $notif->title }}
                                </h4>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                                    {{ $notif->message }}
                                </p>
                                <span class="text-[10px] text-slate-400 block mt-1">
                                    {{ $notif->created_at->diffForHumans() }}
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0">
                            @if ($notif->event)
                                <a href="{{ route('events.show', $notif->event->slug) }}" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold transition inline-block">
                                    {{ __('View Event') }} →
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="pt-4">
                    {{ $notifications->links() }}
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center space-y-3">
                <span class="text-4xl block">🔔</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('No New Event Alerts') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                    {{ __('You are completely caught up! When new concerts or gigs are announced near your area, you will be notified here.') }}
                </p>
            </div>
        @endif

    </div>
</x-app-layout>
