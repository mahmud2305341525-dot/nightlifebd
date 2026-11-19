<x-app-layout>
    @include('partials.leaflet')
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

                            {{-- Safe Route Action Button --}}
                            @if ($event->lat && $event->lng)
                                <button type="button"
                                        id="btn-find-safe-routes"
                                        onclick="activateConcertSafeRoutes()"
                                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-1.5 shrink-0 justify-center cursor-pointer">
                                    <span>🛣️</span>
                                    <span>{{ __('Find Safe Routes Here') }}</span>
                                    <span class="sr-only">{{ __('Plan Safe Route Here') }}</span>
                                </button>
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
                                <button type="button"
                                        onclick="activateConcertSafeRoutes()"
                                        class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline text-xs flex items-center gap-1 cursor-pointer">
                                    <span>{{ __('Find Safe Routes Here') }}</span> →
                                    <span class="sr-only">{{ __('Plan Safe Route') }}</span>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- 🛣️ Dedicated Concert Safe Routes Module (Stays 100% in Concert Section) --}}
                @if ($event->lat && $event->lng)
                    <div id="concert-safe-routes" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
                            <div>
                                <h4 class="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                    <span>🛣️</span>
                                    <span>{{ __('Safe Routes to :event', ['event' => $event->title]) }}</span>
                                </h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ __('Multi-objective safety routing comparing Fastest vs Safest vs Well-Lit corridors.') }}
                                </p>
                            </div>
                            <a href="{{ route('route.index', ['dest_lat' => $event->lat, 'dest_lng' => $event->lng, 'dest_name' => ($event->venue ? $event->venue->name : $event->title), 'event_id' => $event->id]) }}"
                               class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 font-semibold shrink-0">
                                <span>{{ __('Fullscreen Route Finder') }}</span> ↗
                            </a>
                        </div>

                        {{-- Destination Venue Auto-Set Badge --}}
                        <div class="p-3.5 rounded-xl bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/80 flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="text-xl">📍</span>
                                <div class="min-w-0">
                                    <div class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase tracking-wider">{{ __('Destination Venue (Concert Location)') }}</div>
                                    <div class="font-bold text-slate-900 dark:text-slate-100 text-sm truncate">
                                        {{ $event->venue ? $event->venue->name : $event->address }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                        {{ $event->address }} · {{ $event->area }}
                                    </div>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 shrink-0">
                                ✅ {{ __('Destination Auto-Set') }}
                            </span>
                        </div>

                        {{-- Controls: Starting Point & Mode --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-200 mb-1 flex items-center justify-between">
                                    <span>A · {{ __('Your Starting Location') }}</span>
                                    <span id="c-start-txt" class="text-[10px] text-slate-400 font-mono"></span>
                                </div>
                                <div class="flex gap-2">
                                    <button id="btn-c-gps" type="button" class="flex-1 py-1.5 px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-lg font-semibold transition flex items-center justify-center gap-1">
                                        <span>📍</span> <span>{{ __('My location') }}</span>
                                    </button>
                                    <button id="btn-c-map" type="button" class="flex-1 py-1.5 px-3 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-lg font-semibold transition flex items-center justify-center gap-1">
                                        <span>🗺️</span> <span>{{ __('Click on map') }}</span>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-200 mb-1">
                                    {{ __('Travel Mode') }}
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" id="btn-c-mode-driving" class="py-1.5 px-3 rounded-lg font-semibold border border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center gap-1 transition">
                                        <span>🚗</span> <span>{{ __('Driving') }}</span>
                                    </button>
                                    <button type="button" id="btn-c-mode-walking" class="py-1.5 px-3 rounded-lg font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center justify-center gap-1 transition">
                                        <span>🚶</span> <span>{{ __('Walking') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Status Notification --}}
                        <div id="c-status" class="text-xs text-indigo-600 dark:text-indigo-400 font-medium flex items-center gap-2">
                            <span>📡</span>
                            <span id="c-status-txt">{{ __('Click "Find Safe Routes Here" to calculate.') }}</span>
                        </div>

                        {{-- Interactive Map --}}
                        <div id="c-map" style="height: 380px" class="rounded-xl border border-slate-300 dark:border-slate-700 z-0"></div>

                        {{-- Pareto Cards / Results --}}
                        <div id="c-results" class="space-y-3 pt-1"></div>
                    </div>
                @endif
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

    @push('scripts')
    @if ($event->lat && $event->lng)
        <script>
            const eventDest = {
                lat: {{ (float) $event->lat }},
                lng: {{ (float) $event->lng }},
                name: @json($event->venue ? $event->venue->name : $event->title),
                address: @json($event->address)
            };

            let cMap = null;
            let cA = null, cB = [eventDest.lat, eventDest.lng];
            let cmA = null, cmB = null;
            let cPicking = null;
            let cMode = 'driving';
            let cLayers = [];
            let cRouteLayers = {};
            let cHasComputed = false;

            const cIcon = (c, t) => L.divIcon({
                className: '',
                html: `<div style="background:${c};color:#fff;width:26px;height:26px;border-radius:50%;text-align:center;line-height:26px;font-weight:700;border:2px solid #fff;box-shadow:0 1px 4px #0006">${t}</div>`,
                iconSize: [26, 26],
                iconAnchor: [13, 13]
            });

            const cGradeCls = g => ({
                A: 'bg-green-100 dark:bg-green-950/60 text-green-800 dark:text-green-300 border-green-300 dark:border-green-800',
                B: 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
                C: 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800',
                D: 'bg-orange-100 dark:bg-orange-950/60 text-orange-800 dark:text-orange-300 border-orange-300 dark:border-orange-800',
                E: 'bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300 border-red-300 dark:border-red-800',
                F: 'bg-red-200 dark:bg-red-900/60 text-red-900 dark:text-red-200 border-red-400 dark:border-red-700',
                U: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700',
            }[g] || 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-700');

            function initConcertMap() {
                if (cMap) return;
                cMap = NL.map('c-map', [eventDest.lat, eventDest.lng], 13);
                cmB = L.marker(cB, { icon: cIcon('#dc2626', 'B') }).addTo(cMap)
                    .bindPopup(`<strong>${NL.esc(eventDest.name)}</strong><br><span class="text-xs text-slate-500">${NL.esc(eventDest.address)}</span>`);

                cMap.on('click', e => {
                    if (cPicking === 'A') {
                        setConcertA(e.latlng.lat, e.latlng.lng);
                        cPicking = null;
                        runConcertRouteCompute();
                    }
                });
            }

            function setConcertA(lat, lng) {
                cA = [lat, lng];
                if (cmA && cMap) cMap.removeLayer(cmA);
                if (cMap) cmA = L.marker(cA, { icon: cIcon('#2563eb', 'A') }).addTo(cMap);
                const txt = document.getElementById('c-start-txt');
                if (txt) txt.textContent = lat.toFixed(4) + ', ' + lng.toFixed(4);
            }

            function clearConcertRoutes() {
                if (!cMap) return;
                cLayers.forEach(l => cMap.removeLayer(l));
                cLayers = [];
                cRouteLayers = {};
            }

            function highlightConcertRoute(key) {
                Object.entries(cRouteLayers).forEach(([k, ll]) => {
                    ll.forEach(l => {
                        l.setStyle({ opacity: k === key ? 0.95 : 0.25, weight: k === key ? 7 : 3 });
                        if (k === key) l.bringToFront();
                    });
                });
                document.querySelectorAll('.c-pareto-card').forEach(c => {
                    c.classList.toggle('ring-2', c.dataset.key === key);
                    c.classList.toggle('ring-indigo-500', c.dataset.key === key);
                    c.classList.toggle('shadow-lg', c.dataset.key === key);
                });
            }

            async function runConcertRouteCompute() {
                if (!cA || !cB) return;
                const statusTxt = document.getElementById('c-status-txt');
                const resultsEl = document.getElementById('c-results');
                if (statusTxt) statusTxt.innerHTML = '<svg class="inline w-3.5 h-3.5 mr-1.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg> ' + @json(__('Computing safest corridors to concert venue…'));

                const r = await NL.post('{{ route('route.compute') }}', {
                    from_lat: cA[0],
                    from_lng: cA[1],
                    to_lat: cB[0],
                    to_lng: cB[1],
                    bucket: '{{ $bucket }}',
                    mode: cMode
                });

                if (!r.ok) {
                    if (statusTxt) statusTxt.textContent = @json(__('Could not compute route: ')) + ((r.data && (r.data.error || r.data.message)) || 'Error');
                    return;
                }

                const d = r.data;
                cHasComputed = true;
                clearConcertRoutes();

                if (statusTxt) statusTxt.textContent = '✅ ' + (d.summary || @json(__('Safe routes calculated!')));

                // Draw background routes
                d.routes.forEach((rt, i) => {
                    if (i === d.fastest || i === d.safest || i === d.well_lit) return;
                    cLayers.push(L.polyline(rt.coords, { color: '#94a3b8', weight: 2, opacity: .3, dashArray: '6 8' }).addTo(cMap));
                });

                // Key routes
                const routeColors = { fastest: '#64748b', safest: '#16a34a', well_lit: '#7c3aed' };
                const routeLabels = { fastest: @json(__('Fastest')), safest: @json(__('Safest')), well_lit: @json(__('Well-Lit')) };

                ['fastest', 'safest', 'well_lit'].forEach(key => {
                    const idx = d[key];
                    if (idx === undefined || idx === null) return;
                    const rt = d.routes[idx];
                    if (!rt) return;
                    const color = routeColors[key];
                    const pl = L.polyline(rt.coords, { color, weight: key === 'safest' ? 7 : 5, opacity: key === 'safest' ? 0.95 : 0.7 }).addTo(cMap).bindPopup(routeLabels[key]);
                    if (!cRouteLayers[key]) cRouteLayers[key] = [];
                    cRouteLayers[key].push(pl);
                    cLayers.push(pl);
                });

                // Safe havens along safest route
                const safe = d.routes[d.safest];
                (safe.haven_list || []).forEach(h => cLayers.push(
                    L.circleMarker([h.lat, h.lng], { radius: 7, color: '#2563eb', fillColor: '#60a5fa', fillOpacity: .9 }).addTo(cMap).bindPopup(NL.esc(h.type + ': ' + h.name))
                ));

                // Fit map
                const safeLine = cRouteLayers.safest?.[0];
                if (safeLine && cMap) cMap.fitBounds(safeLine.getBounds(), { padding: [30, 30] });

                // Pareto cards
                const t = d.tradeoff;
                const buildCard = (key, obj, color, emoji) => {
                    if (!obj) return '';
                    return `<div class="c-pareto-card bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm border-l-4 p-3.5 cursor-pointer transition-all hover:shadow-md"
                                data-key="${key}"
                                style="border-left-color:${color}"
                                onclick="highlightConcertRoute('${key}')">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-base">${emoji}</span>
                                <span class="font-bold text-xs" style="color:${color}">${NL.esc(obj.label)}</span>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border ${cGradeCls(obj.grade)}">
                                ${obj.grade === 'U' ? @json(__('Unrated')) : ('@lang('Grade') ' + obj.grade)}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-700 dark:text-slate-300">
                            <div>🛣️ ${obj.distance_km} km</div>
                            <div>⏱️ ${obj.duration_min} min</div>
                            <div>⚠️ ${obj.incidents} @lang('incidents')</div>
                            <div>🌑 ${obj.dark_spots} @lang('dark spots')</div>
                            <div>🏥 ${obj.havens} @lang('help pts')</div>
                            <div>📊 ${obj.risk_per_km} @lang('risk/km')</div>
                        </div>
                        ${obj.extra_min > 0 ? `<div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 rounded px-2 py-0.5">+${obj.extra_min} min · +${obj.extra_km} km @lang('vs fastest')</div>` : ''}
                        ${obj.recommendation_reason ? `<div class="mt-2 text-[11px] text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 rounded p-1.5 border border-slate-200 dark:border-slate-700 flex items-start gap-1"><span class="text-xs">💡</span><span>${NL.esc(obj.recommendation_reason)}</span></div>` : ''}
                    </div>`;
                };

                let cardsHTML = `<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">`;
                cardsHTML += buildCard('safest', t.safest, '#16a34a', '🛡️');
                cardsHTML += buildCard('fastest', t.fastest, '#64748b', '⚡');
                if (t.well_lit) cardsHTML += buildCard('well_lit', t.well_lit, '#7c3aed', '💡');
                cardsHTML += `</div>`;

                if (!t.same_route) {
                    const safest = t.safest;
                    cardsHTML += `<div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-950/40 dark:to-emerald-950/30 rounded-xl border border-green-200 dark:border-green-800 p-3 text-xs text-green-800 dark:text-green-300">
                        🎯 <strong>@lang('Cost of Safety'):</strong> @lang('Trading') <strong>${safest.extra_min} @lang('minute(s)')</strong> buys a <strong>@lang('Grade') ${NL.esc(safest.grade)}</strong> route avoiding <strong>${Math.max(0, t.fastest.incidents - safest.incidents)}</strong> incident area(s) and passing <strong>${safest.havens}</strong> help points.
                    </div>`;
                }

                if (resultsEl) resultsEl.innerHTML = cardsHTML;
                highlightConcertRoute('safest');
            }

            function activateConcertSafeRoutes() {
                const el = document.getElementById('concert-safe-routes');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                initConcertMap();
                if (cMap) cMap.invalidateSize();

                if (!cA) {
                    const statusTxt = document.getElementById('c-status-txt');
                    if (statusTxt) statusTxt.textContent = @json(__('Acquiring your location via GPS…'));
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            p => {
                                setConcertA(p.coords.latitude, p.coords.longitude);
                                runConcertRouteCompute();
                            },
                            () => {
                                // Sensible Dhaka starting point fallback so safe routes are displayed immediately!
                                setConcertA(23.7806, 90.4075);
                                const statusTxt = document.getElementById('c-status-txt');
                                if (statusTxt) statusTxt.textContent = @json(__('Auto-selected starting location. Click map or "My location" to adjust.'));
                                runConcertRouteCompute();
                            },
                            { enableHighAccuracy: true, timeout: 3500 }
                        );
                    } else {
                        setConcertA(23.7806, 90.4075);
                        runConcertRouteCompute();
                    }
                } else if (!cHasComputed) {
                    runConcertRouteCompute();
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                const btnGps = document.getElementById('btn-c-gps');
                const btnMap = document.getElementById('btn-c-map');
                const btnDrive = document.getElementById('btn-c-mode-driving');
                const btnWalk = document.getElementById('btn-c-mode-walking');

                if (btnGps) {
                    btnGps.onclick = () => {
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(
                                p => { setConcertA(p.coords.latitude, p.coords.longitude); runConcertRouteCompute(); },
                                err => alert(@json(__('GPS failed: ')) + err.message),
                                { enableHighAccuracy: true, timeout: 8000 }
                            );
                        }
                    };
                }

                if (btnMap) {
                    btnMap.onclick = () => {
                        cPicking = 'A';
                        const statusTxt = document.getElementById('c-status-txt');
                        if (statusTxt) statusTxt.textContent = @json(__('Click anywhere on the map to set starting point A.'));
                    };
                }

                if (btnDrive && btnWalk) {
                    btnDrive.onclick = () => {
                        cMode = 'driving';
                        btnDrive.className = 'py-1.5 px-3 rounded-lg font-semibold border border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center gap-1 transition';
                        btnWalk.className = 'py-1.5 px-3 rounded-lg font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center justify-center gap-1 transition';
                        if (cA) runConcertRouteCompute();
                    };
                    btnWalk.onclick = () => {
                        cMode = 'walking';
                        btnWalk.className = 'py-1.5 px-3 rounded-lg font-semibold border border-indigo-600 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center gap-1 transition';
                        btnDrive.className = 'py-1.5 px-3 rounded-lg font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center justify-center gap-1 transition';
                        if (cA) runConcertRouteCompute();
                    };
                }

                if (new URLSearchParams(window.location.search).has('safe_route') || window.location.hash === '#concert-safe-routes') {
                    setTimeout(activateConcertSafeRoutes, 300);
                }
            });
        </script>
    @endif
    @endpush
</x-app-layout>
