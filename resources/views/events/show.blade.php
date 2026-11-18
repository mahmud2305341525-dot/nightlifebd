<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('events.index') }}" class="p-2 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition text-sm">
                    ←
                </a>
                <div>
                    <h2 class="font-bold text-xl text-slate-900 dark:text-slate-100">
                        {{ $event->title }}
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ $event->categoryLabel() }} · 📍 {{ $event->area }}, Dhaka
                    </p>
                </div>
            </div>

            @if ($userBooking)
                <a href="{{ route('events.bookings.show', $userBooking->booking_reference) }}" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm transition flex items-center gap-1.5 shrink-0">
                    <span>🎟️</span>
                    <span>{{ __('View Your Pass (:ref)', ['ref' => $userBooking->booking_reference]) }}</span>
                </a>
            @endif
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        {{-- Existing Booking Alert Banner --}}
        @if ($userBooking)
            <div class="rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-950/40 dark:to-teal-950/30 border-2 border-emerald-300 dark:border-emerald-700/80 p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="text-3xl p-2 rounded-xl bg-emerald-100 dark:bg-emerald-900/60">✅</span>
                    <div>
                        <h4 class="font-bold text-sm text-emerald-950 dark:text-emerald-200">{{ __('You have booked passes for this event!') }}</h4>
                        <p class="text-xs text-emerald-800 dark:text-emerald-300/90 mt-0.5">
                            {{ __('Booking Ref: :ref · :qty Pass(es) · Status: :status', [
                                'ref' => $userBooking->booking_reference,
                                'qty' => $userBooking->quantity,
                                'status' => ucfirst($userBooking->status)
                            ]) }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('events.bookings.show', $userBooking->booking_reference) }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-sm transition shrink-0 text-center">
                    {{ __('Open Digital QR Ticket') }} →
                </a>
            </div>
        @endif

        {{-- Main Event Showcase Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Left 2 Columns: Description & Safety Companion --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Event Info Card --}}
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/80">
                            {{ $event->categoryLabel() }}
                        </span>
                        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                            <span>Organized by: </span>
                            <strong class="text-slate-800 dark:text-slate-200">{{ $event->organizer->name }}</strong>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 space-y-1">
                            <span class="text-slate-400 font-medium block">🗓️ {{ __('Event Date & Hours') }}</span>
                            <div class="font-bold text-slate-900 dark:text-slate-100 text-sm">
                                {{ $event->starts_at->format('D, d M Y') }}
                            </div>
                            <div class="text-slate-500 dark:text-slate-400">
                                {{ $event->starts_at->format('h:i A') }} — {{ $event->ends_at->format('h:i A') }}
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 space-y-1">
                            <span class="text-slate-400 font-medium block">📍 {{ __('Location & Venue') }}</span>
                            <div class="font-bold text-slate-900 dark:text-slate-100 text-sm truncate">
                                {{ $event->venue ? $event->venue->name : $event->address }}
                            </div>
                            <div class="text-slate-500 dark:text-slate-400 truncate">
                                {{ $event->address }}
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">{{ __('About This Event') }}</h4>
                        <div class="prose prose-slate dark:prose-invert text-xs leading-relaxed text-slate-700 dark:text-slate-300">
                            {!! nl2br(e($event->description)) !!}
                        </div>
                    </div>
                </div>

                {{-- Venue Safety Intelligence Integration --}}
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <span>🛡️</span>
                            <span>{{ __('NightLifeBD Safety Intelligence for This Venue') }}</span>
                        </h4>
                        @if ($event->venue)
                            <a href="{{ route('venues.show', $event->venue) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ __('Inspect Venue Breakdown') }} →
                            </a>
                        @endif
                    </div>

                    @if ($safetyScore)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center font-extrabold text-lg {{ $safetyScore->score >= 70 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($safetyScore->score >= 50 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300') }}">
                                    {{ round($safetyScore->score) }}
                                </div>
                                <div>
                                    <div class="font-bold text-xs text-slate-900 dark:text-slate-100">
                                        {{ $safetyScore->score >= 70 ? __('High Safety Environment') : ($safetyScore->score >= 50 ? __('Moderate Caution Rating') : __('High Risk / Caution Notice')) }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ __('Calculated for :bucket hours', ['bucket' => \App\Support\TimeBucket::label($bucket)]) }}
                                    </div>
                                </div>
                            </div>

                            {{-- Safe Route Deep Link --}}
                            @if ($event->lat && $event->lng)
                                <a href="{{ route('route.index', ['dest_lat' => $event->lat, 'dest_lng' => $event->lng, 'dest_name' => $event->title]) }}"
                                   class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5 shrink-0 justify-center">
                                    <span>🛣️</span>
                                    <span>{{ __('Plan Safe Route Here') }}</span>
                                </a>
                            @endif
                        </div>

                        {{-- Safety Checklist Points --}}
                        @if ($event->venue)
                            <div class="grid grid-cols-3 gap-2.5 text-center text-xs">
                                <div class="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 {{ $event->venue->lighting >= 4 ? 'bg-emerald-50/60 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-300' : 'text-slate-600 dark:text-slate-400' }}">
                                    <div class="font-bold">💡 {{ __('Lighting') }}</div>
                                    <div class="text-[11px] mt-0.5">{{ $event->venue->lighting }}/5 {{ __('Stars') }}</div>
                                </div>
                                <div class="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 {{ $event->venue->has_cctv ? 'bg-emerald-50/60 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-300' : 'text-slate-600 dark:text-slate-400' }}">
                                    <div class="font-bold">📹 {{ __('CCTV') }}</div>
                                    <div class="text-[11px] mt-0.5">{{ $event->venue->has_cctv ? __('Monitored') : __('None') }}</div>
                                </div>
                                <div class="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 {{ $event->venue->has_guard ? 'bg-emerald-50/60 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-300' : 'text-slate-600 dark:text-slate-400' }}">
                                    <div class="font-bold">👮 {{ __('Security') }}</div>
                                    <div class="text-[11px] mt-0.5">{{ $event->venue->has_guard ? __('Guard on Duty') : __('Unstaffed') }}</div>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 text-xs text-slate-500 dark:text-slate-400 flex items-center justify-between">
                            <span>🛡️ {{ __('Safety is verified by on-site event staff and security personnel.') }}</span>
                            @if ($event->lat && $event->lng)
                                <a href="{{ route('route.index', ['dest_lat' => $event->lat, 'dest_lng' => $event->lng, 'dest_name' => $event->title]) }}"
                                   class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                    {{ __('Plan Safe Route') }} →
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

            </div>

            {{-- Right 1 Column: Ticket Booking Form --}}
            <div class="space-y-6">

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-5 sticky top-20">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                        <h3 class="font-bold text-base text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <span>🎟️</span>
                            <span>{{ __('Book Your Ticket') }}</span>
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ __('Choose tier and receive your verified QR Pass instantly.') }}
                        </p>
                    </div>

                    @auth
                        <form method="POST" action="{{ route('events.book', $event->id) }}" class="space-y-4">
                            @csrf

                            {{-- Ticket Tier Radio Selector --}}
                            <div class="space-y-2.5">
                                <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 block">
                                    {{ __('Select Pass Tier') }}:
                                </label>

                                @foreach ($event->ticketTiers as $idx => $tier)
                                    @php $available = $tier->isAvailable(); @endphp
                                    <label class="flex items-start justify-between p-3 rounded-xl border {{ $available ? 'border-slate-200 dark:border-slate-700 hover:border-indigo-500 dark:hover:border-indigo-400 cursor-pointer bg-slate-50/50 dark:bg-slate-800/40' : 'border-slate-200 dark:border-slate-800 opacity-60 cursor-not-allowed bg-slate-100 dark:bg-slate-800/20' }} transition">
                                        <div class="flex items-start gap-2.5">
                                            <input type="radio" name="ticket_tier_id" value="{{ $tier->id }}"
                                                   {{ $idx === 0 && $available ? 'checked' : '' }}
                                                   {{ ! $available ? 'disabled' : '' }}
                                                   class="mt-1 text-indigo-600 focus:ring-indigo-500">
                                            <div>
                                                <div class="text-xs font-bold text-slate-900 dark:text-slate-100">
                                                    {{ $tier->name }}
                                                </div>
                                                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                    @if ($available)
                                                        {{ __(':rem remaining', ['rem' => $tier->remainingCount()]) }}
                                                    @else
                                                        <span class="text-red-600 dark:text-red-400 font-bold">{{ __('Sold Out') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            @if ($tier->price <= 0)
                                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ __('Free') }}</span>
                                            @else
                                                <span class="text-xs font-bold text-slate-900 dark:text-slate-100">৳{{ number_format($tier->price) }}</span>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>

                            {{-- Quantity Selector --}}
                            <div>
                                <label for="quantity" class="text-xs font-semibold text-slate-700 dark:text-slate-300 block mb-1">
                                    {{ __('Number of Tickets') }}:
                                </label>
                                <select id="quantity" name="quantity" class="w-full rounded-xl text-xs border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
                                    @for ($q = 1; $q <= 5; $q++)
                                        <option value="{{ $q }}">{{ $q }} {{ $q === 1 ? __('Pass') : __('Passes') }}</option>
                                    @endfor
                                </select>
                            </div>

                            {{-- Payment Method Selection --}}
                            <div>
                                <label class="text-xs font-semibold text-slate-700 dark:text-slate-300 block mb-1">
                                    {{ __('Checkout Method') }}:
                                </label>
                                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                    <label class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:border-indigo-500 cursor-pointer block">
                                        <input type="radio" name="payment_method" value="mock_bkash" checked class="sr-only peer">
                                        <div class="peer-checked:font-bold peer-checked:text-pink-600 dark:peer-checked:text-pink-400">
                                            <span>📱</span>
                                            <span class="block text-[11px] mt-0.5">bKash</span>
                                        </div>
                                    </label>
                                    <label class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:border-indigo-500 cursor-pointer block">
                                        <input type="radio" name="payment_method" value="mock_nagad" class="sr-only peer">
                                        <div class="peer-checked:font-bold peer-checked:text-orange-600 dark:peer-checked:text-orange-400">
                                            <span>💳</span>
                                            <span class="block text-[11px] mt-0.5">Nagad</span>
                                        </div>
                                    </label>
                                    <label class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:border-indigo-500 cursor-pointer block">
                                        <input type="radio" name="payment_method" value="free" class="sr-only peer">
                                        <div class="peer-checked:font-bold peer-checked:text-indigo-600 dark:peer-checked:text-indigo-400">
                                            <span>🎟️</span>
                                            <span class="block text-[11px] mt-0.5">Pass/RSVP</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            {{-- Submit Button --}}
                            <div class="pt-2">
                                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:scale-[.98] text-white font-bold text-xs shadow-md transition flex items-center justify-center gap-2">
                                    <span>🎟️</span>
                                    <span>{{ __('Confirm Booking & Generate Pass') }}</span>
                                </button>
                                <p class="text-[10px] text-center text-slate-400 dark:text-slate-500 mt-2">
                                    🔒 {{ __('Instant QR Pass with integrated emergency companion.') }}
                                </p>
                            </div>
                        </form>
                    @else
                        <div class="text-center py-6 space-y-3">
                            <span class="text-3xl block">🔒</span>
                            <h4 class="font-bold text-sm text-slate-900 dark:text-slate-100">{{ __('Sign in to book tickets') }}</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Create an account or sign in to receive your personalized digital QR ticket pass and safety companion.') }}
                            </p>
                            <div class="pt-2 flex justify-center gap-2">
                                <a href="{{ route('login') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-sm transition">
                                    {{ __('Login') }}
                                </a>
                                <a href="{{ route('register') }}" class="px-4 py-2 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                    {{ __('Register') }}
                                </a>
                            </div>
                        </div>
                    @endauth

                </div>

            </div>

        </div>

    </div>
</x-app-layout>
