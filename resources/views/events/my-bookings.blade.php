<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span>🎟️</span>
                    <span>{{ __('My Event Passes & Bookings') }}</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('View and manage your digital event passes, safety routes, and check-in QR codes.') }}
                </p>
            </div>
            <a href="{{ route('events.index') }}" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-sm transition flex items-center gap-1.5 shrink-0">
                <span>+</span>
                <span>{{ __('Browse More Events') }}</span>
            </a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        @if ($bookings->count() > 0)
            <div class="space-y-4">
                @foreach ($bookings as $booking)
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-600 transition flex flex-col md:flex-row md:items-center justify-between gap-4">
                        
                        <div class="space-y-2 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/70 border border-indigo-200 dark:border-indigo-800">
                                    {{ $booking->booking_reference }}
                                </span>

                                @if ($booking->isCheckedIn())
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        ✅ {{ __('Checked In') }}
                                    </span>
                                @elseif ($booking->isCancelled())
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300">
                                        ❌ {{ __('Cancelled') }}
                                    </span>
                                @else
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                        🔵 {{ __('Confirmed') }}
                                    </span>
                                @endif

                                <span class="text-xs text-slate-400">
                                    · {{ $booking->ticketTier->name }} ({{ $booking->quantity }}x)
                                </span>
                            </div>

                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                                <a href="{{ route('events.show', $booking->event->slug) }}" class="hover:underline">
                                    {{ $booking->event->title }}
                                </a>
                            </h3>

                            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                                <span>🗓️ {{ $booking->event->starts_at->format('D, d M Y · h:i A') }}</span>
                                <span>📍 {{ $booking->event->venue ? $booking->event->venue->name : $booking->event->address }}</span>
                                <span>💳 {{ $booking->total_price <= 0 ? __('Free RSVP') : '৳' . number_format($booking->total_price) }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('events.bookings.show', $booking->booking_reference) }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5">
                                <span>🎟️</span>
                                <span>{{ __('Open Pass (QR)') }}</span>
                            </a>

                            @if (! $booking->isCheckedIn() && ! $booking->isCancelled())
                                <form method="POST" action="{{ route('events.bookings.cancel', $booking->id) }}" onsubmit="return confirm('{{ __('Are you sure you want to cancel this ticket pass?') }}')">
                                    @csrf
                                    <button type="submit" class="px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 font-semibold transition">
                                        {{ __('Cancel') }}
                                    </button>
                                </form>
                            @endif
                        </div>

                    </div>
                @endforeach

                <div class="pt-4">
                    {{ $bookings->links() }}
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center space-y-3">
                <span class="text-4xl block">🎟️</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('No Event Passes Yet') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                    {{ __('You have not booked any concert or night event passes yet. Browse upcoming live gigs in Dhaka and secure your tickets.') }}
                </p>
                <div class="pt-2">
                    <a href="{{ route('events.index') }}" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-sm transition inline-block">
                        {{ __('Discover Night Events') }} →
                    </a>
                </div>
            </div>
        @endif

    </div>
</x-app-layout>
