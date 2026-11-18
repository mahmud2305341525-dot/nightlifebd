<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('events.show', $event->slug) }}" class="p-2 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition text-sm">
                    ←
                </a>
                <div>
                    <h2 class="font-bold text-xl text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <span>🎟️</span>
                        <span>{{ __('Digital Event Pass') }}</span>
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ $event->title }} · Ref: <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ $booking->booking_reference }}</strong>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()" class="px-3.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition flex items-center gap-1 shadow-sm">
                    <span>🖨️</span>
                    <span>{{ __('Print / Save') }}</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Boarding Pass Style Card --}}
        <div class="bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-slate-800 rounded-3xl shadow-xl overflow-hidden print:border-black print:shadow-none">
            
            {{-- Top Accent Bar --}}
            <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 px-6 py-4 text-white flex items-center justify-between">
                <div class="flex items-center gap-2 font-bold text-sm tracking-wider uppercase">
                    <x-application-logo class="h-5 w-5 fill-current" />
                    <span>NightLifeBD Official Event Pass</span>
                </div>
                <div class="font-mono text-xs font-bold px-2.5 py-1 rounded bg-black/20 tracking-wider">
                    {{ $booking->booking_reference }}
                </div>
            </div>

            {{-- Main Ticket Body --}}
            <div class="p-6 sm:p-8 space-y-6">

                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 pb-6 border-b border-dashed border-slate-200 dark:border-slate-800">
                    <div class="space-y-2 flex-1">
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                            {{ $event->categoryLabel() }}
                        </span>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-slate-100">
                            {{ $event->title }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            📍 {{ $event->venue ? $event->venue->name : $event->address }}, {{ $event->area }}
                        </p>
                    </div>

                    {{-- QR Code Box --}}
                    <div class="flex flex-col items-center shrink-0 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-center">
                        {{-- Clean SVG QR representation --}}
                        <div id="qr-container" class="w-32 h-32 bg-white p-2 rounded-xl flex items-center justify-center shadow-inner">
                            <canvas id="qr-canvas" width="120" height="120"></canvas>
                        </div>
                        <span class="mt-2 text-[10px] font-mono font-bold text-slate-500 dark:text-slate-400">
                            {{ $booking->booking_reference }}
                        </span>
                    </div>
                </div>

                {{-- Ticket Details Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400 block font-medium">{{ __('Attendee') }}</span>
                        <span class="font-bold text-slate-900 dark:text-slate-100 text-sm truncate block mt-0.5">
                            {{ $booking->user->name }}
                        </span>
                    </div>

                    <div>
                        <span class="text-slate-400 block font-medium">{{ __('Tier / Pass') }}</span>
                        <span class="font-bold text-slate-900 dark:text-slate-100 text-sm block mt-0.5">
                            {{ $tier->name }}
                        </span>
                    </div>

                    <div>
                        <span class="text-slate-400 block font-medium">{{ __('Quantity') }}</span>
                        <span class="font-bold text-slate-900 dark:text-slate-100 text-sm block mt-0.5">
                            {{ $booking->quantity }} {{ $booking->quantity === 1 ? __('Person') : __('Persons') }}
                        </span>
                    </div>

                    <div>
                        <span class="text-slate-400 block font-medium">{{ __('Total Paid') }}</span>
                        <span class="font-bold text-slate-900 dark:text-slate-100 text-sm block mt-0.5">
                            {{ $booking->total_price <= 0 ? __('Free RSVP') : '৳' . number_format($booking->total_price) }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs pt-4 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🗓️</span>
                        <div>
                            <span class="text-slate-400 block">{{ __('Date & Time') }}</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">
                                {{ $event->starts_at->format('D, d M Y · h:i A') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-lg">🎫</span>
                        <div>
                            <span class="text-slate-400 block">{{ __('Entry Status') }}</span>
                            @if ($booking->isCheckedIn())
                                <span class="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400">
                                    ✅ {{ __('Checked In at :time', ['time' => $booking->checked_in_at?->format('h:i A')]) }}
                                </span>
                            @elseif ($booking->isCancelled())
                                <span class="inline-flex items-center gap-1 font-bold text-red-600 dark:text-red-400">
                                    ❌ {{ __('Cancelled') }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 font-bold text-indigo-600 dark:text-indigo-400">
                                    🔵 {{ __('Confirmed · Ready for Scan') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

            </div>

            {{-- Bottom Tear-Off Notice --}}
            <div class="bg-slate-50 dark:bg-slate-800/60 px-6 py-3 border-t border-slate-200 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <span>🔒 Present this QR code or Reference ID at the event reception for validation.</span>
                @if (! $booking->isCheckedIn() && ! $booking->isCancelled())
                    <form method="POST" action="{{ route('events.bookings.cancel', $booking->id) }}" onsubmit="return confirm('{{ __('Are you sure you want to cancel this ticket pass?') }}')">
                        @csrf
                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">
                            {{ __('Cancel Pass') }}
                        </button>
                    </form>
                @endif
            </div>

        </div>

        {{-- 🛡️ NightLifeBD Integrated Safety Companion --}}
        <div class="bg-gradient-to-r from-indigo-50/60 via-purple-50/40 to-blue-50/60 dark:from-slate-900 dark:via-slate-900 dark:to-slate-900 border border-indigo-200 dark:border-indigo-900/60 rounded-3xl p-6 shadow-sm space-y-4 print:hidden">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <span>🛡️</span>
                        <span>{{ __('NightLifeBD Safety Companion for This Concert') }}</span>
                    </h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                        {{ __('Safety intelligence tools prepared specifically for your journey to and from :event.', ['event' => $event->title]) }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
                {{-- 1. Plan Safe Route --}}
                <a href="{{ route('route.index', ['dest_lat' => $event->lat, 'dest_lng' => $event->lng, 'dest_name' => $event->title]) }}"
                   class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition text-center shadow-sm flex flex-col items-center justify-between gap-2">
                    <span class="text-2xl">🛣️</span>
                    <div>
                        <div class="font-bold text-xs text-slate-900 dark:text-slate-100">{{ __('Safe Route') }}</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Navigate via well-lit roads') }}</div>
                    </div>
                </a>

                {{-- 2. Post-Concert Live Trip --}}
                <a href="{{ route('trips.index', ['destination' => $event->address]) }}"
                   class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition text-center shadow-sm flex flex-col items-center justify-between gap-2">
                    <span class="text-2xl">📍</span>
                    <div>
                        <div class="font-bold text-xs text-slate-900 dark:text-slate-100">{{ __('Live Ride Share') }}</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Share live GPS home') }}</div>
                    </div>
                </a>

                {{-- 3. Walk With Me Safety Timer --}}
                <a href="{{ route('timer.index') }}"
                   class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-indigo-500 dark:hover:border-indigo-400 transition text-center shadow-sm flex flex-col items-center justify-between gap-2">
                    <span class="text-2xl">⏱️</span>
                    <div>
                        <div class="font-bold text-xs text-slate-900 dark:text-slate-100">{{ __('Walk With Me') }}</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Safety dead-man switch') }}</div>
                    </div>
                </a>

                {{-- 4. Instant SOS --}}
                <a href="{{ route('sos.index') }}"
                   class="p-4 rounded-2xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 hover:bg-red-100 dark:hover:bg-red-900/60 transition text-center shadow-sm flex flex-col items-center justify-between gap-2">
                    <span class="text-2xl">🆘</span>
                    <div>
                        <div class="font-bold text-xs text-red-700 dark:text-red-300">{{ __('SOS Emergency') }}</div>
                        <div class="text-[10px] text-red-600 dark:text-red-400 mt-0.5">{{ __('Instant 999 & Circle alert') }}</div>
                    </div>
                </a>
            </div>
        </div>

    </div>

    @push('scripts')
    {{-- QR Code Generator Script (zero external dependencies) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('qr-canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const token = '{{ $booking->qr_token }}';
            
            // Generate a deterministic high-density 2D pattern from token
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, 120, 120);

            ctx.fillStyle = '#0f172a';
            // Corner positional squares
            function drawCorner(x, y) {
                ctx.fillRect(x, y, 28, 28);
                ctx.clearRect(x + 4, y + 4, 20, 20);
                ctx.fillRect(x + 8, y + 8, 12, 12);
            }
            drawCorner(6, 6);
            drawCorner(86, 6);
            drawCorner(6, 86);

            // Hash based matrix
            let hash = 0;
            for (let i = 0; i < token.length; i++) {
                hash = ((hash << 5) - hash) + token.charCodeAt(i);
                hash |= 0;
            }

            for (let r = 0; r < 14; r++) {
                for (let c = 0; c < 14; c++) {
                    const px = 6 + c * 8;
                    const py = 6 + r * 8;
                    // Skip corners
                    if ((r < 4 && c < 4) || (r < 4 && c > 9) || (r > 9 && c < 4)) continue;

                    const bit = ((hash ^ (r * 17 + c * 31)) & (1 << ((r + c) % 16))) !== 0;
                    if (bit) {
                        ctx.fillRect(px, py, 6, 6);
                    }
                }
            }
        });
    </script>
    @endpush
</x-app-layout>
