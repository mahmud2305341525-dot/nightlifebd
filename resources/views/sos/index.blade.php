<x-app-layout>
    {{-- Full-screen strobe overlay for night deterrence --}}
    <div id="strobe-overlay" class="hidden fixed inset-0 z-[99999] pointer-events-none transition-colors duration-75"></div>

    <x-slot name="header">
        <h1 class="font-extrabold text-2xl text-red-600 flex items-center gap-2">🆘 SOS / জরুরি সাহায্য</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Hold the red button for 3 seconds or double-tap it. Works from every page via the floating button.') }}</p>
    </x-slot>

    <div class="max-w-2xl mx-auto p-4 space-y-4">

        {{-- Big pulsing SOS button --}}
        <div class="flex justify-center py-2">
            <div class="relative">
                <span class="absolute inset-0 rounded-full bg-red-400 opacity-50 animate-ping"></span>
                <button type="button"
                    onclick="window.NLSOS_TRIGGER && window.NLSOS_TRIGGER()"
                    class="relative w-44 h-44 rounded-full bg-red-600 hover:bg-red-500 active:scale-95 text-white text-4xl font-extrabold shadow-2xl ring-8 ring-red-200 dark:ring-red-950/60 transition-transform duration-150 select-none cursor-pointer"
                    style="touch-action:manipulation;-webkit-user-select:none;user-select:none"
                    title="{{ __('Hold 3 seconds or double-tap') }}">
                    SOS
                </button>
            </div>
        </div>

        {{-- Deterrent Siren & Strobe Alarm --}}
        <div class="bg-gradient-to-r from-red-600 via-rose-600 to-red-700 text-white rounded-2xl p-4 shadow-lg border border-red-500 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-2xl shrink-0">
                    🔊
                </div>
                <div>
                    <div class="font-black text-sm uppercase tracking-wide flex items-center gap-1.5">
                        <span>{{ __('Deterrent Siren & Strobe') }}</span>
                        <span class="text-[10px] bg-red-900/60 px-2 py-0.5 rounded-full font-normal">110dB Alarm</span>
                    </div>
                    <div class="text-xs text-red-100 mt-0.5 leading-snug">
                        {{ __('Loud screeching siren & flashing screen strobe to scare off muggers and draw public attention.') }}
                    </div>
                </div>
            </div>
            <button type="button" id="siren-btn" onclick="toggleEmergencySiren()"
                class="px-4 py-2.5 rounded-xl font-extrabold text-xs bg-white text-red-700 hover:bg-red-50 active:scale-95 transition shadow-md shrink-0">
                🚨 {{ __('START SIREN') }}
            </button>
        </div>

        {{-- Direct call 999 --}}
        <a href="tel:999" class="flex items-center justify-center gap-2 rounded-xl bg-slate-900 dark:bg-slate-800 text-white py-3.5 text-base font-bold shadow-md hover:bg-slate-800 dark:hover:bg-slate-700 border border-slate-700 transition">
            📞 {{ __('Call 999 (Police / Ambulance / Fire)') }}
        </a>

        {{-- GPS status --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 flex items-center gap-3">
            <div id="gps-icon" class="text-2xl">⏳</div>
            <div>
                <div class="font-semibold text-sm text-slate-800 dark:text-slate-100">{{ __('Location status') }}</div>
                <div id="gps-status" class="text-sm text-slate-500 dark:text-slate-400">{{ __('Checking your GPS…') }}</div>
            </div>
        </div>

        {{-- Emergency contacts & WhatsApp SOS --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 space-y-3">
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-1">👥 {{ __('Your emergency contacts') }}</h2>
                <a href="{{ route('contacts.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">+ {{ __('Manage') }}</a>
            </div>

            @forelse ($contacts as $c)
                <div class="flex flex-wrap items-center justify-between gap-2 py-2.5 border-b border-slate-100 dark:border-slate-800 last:border-0">
                    <div>
                        <div class="font-semibold text-sm text-slate-800 dark:text-slate-100">{{ $c->name }} <span class="text-xs text-slate-400 dark:text-slate-500 font-normal">· {{ $c->relation }}</span></div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">📱 {{ $c->phone }}</div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" onclick="sendWhatsAppSOS('{{ $c->phone }}', '{{ $c->name }}')"
                            class="min-h-[44px] min-w-[44px] px-3.5 py-2 text-xs bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 rounded-lg font-semibold hover:bg-emerald-200 dark:hover:bg-emerald-900 transition inline-flex items-center justify-center gap-1 shadow-sm"
                            style="min-height: 44px; min-width: 44px;"
                            aria-label="{{ __('Send WhatsApp SOS to :name', ['name' => $c->name]) }}">
                            <span>💬</span> <span>WhatsApp</span>
                        </button>
                        <a href="sms:{{ $c->phone }}"
                            class="min-h-[44px] min-w-[44px] px-3.5 py-2 text-xs bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300 rounded-lg font-semibold hover:bg-red-200 dark:hover:bg-red-900 transition inline-flex items-center justify-center shadow-sm"
                            style="min-height: 44px; min-width: 44px;"
                            aria-label="{{ __('Send SMS to :name', ['name' => $c->name]) }}">
                            SMS
                        </a>
                        <a href="tel:{{ $c->phone }}"
                            class="min-h-[44px] min-w-[44px] px-3 py-2 text-base bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-lg font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition inline-flex items-center justify-center shadow-sm"
                            style="min-height: 44px; min-width: 44px;"
                            aria-label="{{ __('Call :name', ['name' => $c->name]) }}">
                            📞
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-sm text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg p-3">
                    ⚠️ {{ __('No emergency contacts set.') }}
                    <a href="{{ route('contacts.index') }}" class="underline font-semibold ml-1 text-amber-700 dark:text-amber-300">{{ __('Add contacts') }}</a>
                    {{ __('so SOS can alert them automatically.') }}
                </div>
            @endforelse

            {{-- Broadcast SOS via WhatsApp --}}
            <button type="button" onclick="broadcastWhatsAppSOS()"
                class="w-full min-h-[44px] mt-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs py-3 px-4 rounded-xl shadow transition inline-flex items-center justify-center gap-2"
                style="min-height: 44px;"
                aria-label="{{ __('Broadcast SOS to WhatsApp family or group') }}">
                <span>📢</span> <span>{{ __('Broadcast SOS to WhatsApp (Family / Group)') }}</span>
            </button>
        </div>

        {{-- Nearest help points (with offline cache fallback) --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 space-y-2">
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-1">🏥 {{ __('Nearest help points') }}</h2>
                <span id="haven-cache-badge" class="text-[10px] text-slate-400 dark:text-slate-500"></span>
            </div>
            <div id="havens-list" class="text-sm text-slate-500 dark:text-slate-400">{{ __('Enable location to see nearby police, hospitals and pharmacies.') }}</div>
        </div>

        {{-- How it works --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 text-sm space-y-3">
            <h2 class="font-semibold text-slate-800 dark:text-slate-100 mb-1">{{ __('How SOS works in Bangladesh') }}</h2>
            <div class="space-y-1.5 text-xs text-slate-700 dark:text-slate-300">
                <p class="flex items-start gap-1.5"><span>📞</span> <span><strong>{{ __('Emergency Services (999):') }}</strong> {{ __('Tapping 999 directly dials National Emergency Services. 999 is NOT automatically contacted without user dialing.') }}</span></p>
                <p class="flex items-start gap-1.5"><span>⚡</span> <span><strong>{{ __('Works 100% Offline:') }}</strong> {{ __('Direct phone calls, cellular SMS alerts, audio deterrent siren, and cached help points require NO internet connection.') }}</span></p>
                <p class="flex items-start gap-1.5"><span>📶</span> <span><strong>{{ __('Requires Internet:') }}</strong> {{ __('WhatsApp links and automated Telegram/Email notifications require mobile data or Wi-Fi.') }}</span></p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-lg p-2.5 text-[11px] text-slate-600 dark:text-slate-300">
                ℹ️ {{ __('Delivery guarantee: Electronic notifications depend on provider APIs. For life-threatening emergencies, always dial 999 immediately.') }}
            </div>
        </div>

        {{-- Quick links to other safety tools --}}
        <div class="grid grid-cols-3 gap-3 text-center text-sm">
            <a href="{{ route('trips.index') }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-3 hover:shadow-md transition text-slate-800 dark:text-slate-200 block">
                <div class="text-2xl mb-1">📍</div>
                <div class="font-semibold">{{ __('Live trip') }}</div>
            </a>
            <a href="{{ route('timer.index') }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-3 hover:shadow-md transition text-slate-800 dark:text-slate-200 block">
                <div class="text-2xl mb-1">⏱️</div>
                <div class="font-semibold">{{ __('Safety timer') }}</div>
            </a>
            <a href="{{ route('fakecall') }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-3 hover:shadow-md transition text-slate-800 dark:text-slate-200 block">
                <div class="text-2xl mb-1">📞</div>
                <div class="font-semibold">{{ __('Fake call') }}</div>
            </a>
        </div>
    </div>

    @push('scripts')
    <script>
        const gpsIcon   = document.getElementById('gps-icon');
        const gpsStatus = document.getElementById('gps-status');
        const havensList = document.getElementById('havens-list');
        const havenBadge = document.getElementById('haven-cache-badge');
        let currentPos  = null;

        const userName = @json(auth()->user()->name);

        function cleanBdPhone(phone) {
            let digits = String(phone).replace(/\D/g, '');
            if (digits.startsWith('01')) digits = '88' + digits;
            else if (!digits.startsWith('88') && digits.length === 10) digits = '880' + digits;
            return digits;
        }

        function buildSOSMessage() {
            const mapLink = currentPos ? `https://maps.google.com/?q=${currentPos.latitude.toFixed(5)},${currentPos.longitude.toFixed(5)}` : null;
            let msg = `🚨 SOS EMERGENCY! ${userName} needs urgent help in Dhaka!\n`;
            if (mapLink) {
                msg += `📍 Current Location: ${mapLink}\n`;
            } else {
                msg += `📍 GPS coordinates unavailable.\n`;
            }
            msg += `জরুরি সাহায্য প্রয়োজন! আমাকে অবিলম্বে কল করুন অথবা ৯৯৯ এ যোগাযোগ করুন।`;
            return msg;
        }

        function sendWhatsAppSOS(phone, name) {
            const clean = cleanBdPhone(phone);
            const msg = buildSOSMessage();
            const url = `https://wa.me/${clean}?text=${encodeURIComponent(msg)}`;
            window.open(url, '_blank');
        }

        function broadcastWhatsAppSOS() {
            const msg = buildSOSMessage();
            const url = `https://api.whatsapp.com/send?text=${encodeURIComponent(msg)}`;
            window.open(url, '_blank');
        }

        // --- Offline Safe Havens Cache & Fallback ---
        function renderHavens(items, isCached = false) {
            if (!items || !items.length) {
                havensList.textContent = '{{ __("No help points found nearby.") }}';
                return;
            }
            const icons = { police: '👮', hospital: '🏥', pharmacy: '💊' };
            havensList.innerHTML = items.slice(0, 5).map(h => {
                const distKm = h.distance_m ? (h.distance_m / 1000).toFixed(1) : (h.d ? (h.d / 1000).toFixed(1) : '?');
                const eta = h.eta_min ? ` · ~${h.eta_min} min` : '';
                return `<div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-800 last:border-0 text-xs">
                    <span class="text-slate-800 dark:text-slate-200">${icons[h.type] || '📍'} <strong>${h.name}</strong> <span class="text-slate-400 capitalize">(${h.type})</span></span>
                    <span class="text-slate-500 dark:text-slate-400 font-semibold">${distKm} km${eta}</span>
                </div>`;
            }).join('');
            if (isCached && havenBadge) {
                havenBadge.textContent = '⚡ Cached (Offline)';
            }
        }

        // Attempt GPS read
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(
                pos => {
                    currentPos = pos.coords;
                    gpsIcon.textContent   = '✅';
                    gpsStatus.textContent = `{{ __('Location active') }} (${pos.coords.latitude.toFixed(4)}, ${pos.coords.longitude.toFixed(4)}) — {{ __('will be included in SOS.') }}`;
                    gpsStatus.classList.replace('text-slate-500', 'text-green-700');

                    // Fetch nearest help points
                    fetch(`/api/nearest?lat=${pos.coords.latitude}&lng=${pos.coords.longitude}`)
                        .then(r => r.json())
                        .then(items => {
                            renderHavens(items);
                            try { localStorage.setItem('nl_havens_cache', JSON.stringify(items)); } catch(e){}
                        })
                        .catch(() => {
                            try {
                                const cached = JSON.parse(localStorage.getItem('nl_havens_cache') || '[]');
                                renderHavens(cached, true);
                            } catch(e) {
                                havensList.textContent = '{{ __("Could not load help points.") }}';
                            }
                        });
                },
                err => {
                    gpsIcon.textContent   = '❌';
                    gpsStatus.textContent = '{{ __("Location unavailable. SOS will still alert contacts without map coordinates.") }}';
                    gpsStatus.classList.replace('text-slate-500', 'text-amber-700');
                    try {
                        const cached = JSON.parse(localStorage.getItem('nl_havens_cache') || '[]');
                        if (cached.length) renderHavens(cached, true);
                    } catch(e){}
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        }

        // --- Emergency Deterrent Siren & Strobe ---
        let audioCtx = null;
        let osc1 = null, osc2 = null, gainNode = null;
        let sirenTimer = null, strobeTimer = null;
        let isSirenActive = false;

        function toggleEmergencySiren() {
            if (isSirenActive) {
                stopSiren();
            } else {
                startSiren();
            }
        }

        function startSiren() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                audioCtx = new AudioCtx();

                osc1 = audioCtx.createOscillator();
                osc2 = audioCtx.createOscillator();
                gainNode = audioCtx.createGain();

                osc1.type = 'sawtooth';
                osc2.type = 'sine';

                osc1.frequency.setValueAtTime(800, audioCtx.currentTime);
                osc2.frequency.setValueAtTime(850, audioCtx.currentTime);
                gainNode.gain.setValueAtTime(0.85, audioCtx.currentTime);

                osc1.connect(gainNode);
                osc2.connect(gainNode);
                gainNode.connect(audioCtx.destination);

                osc1.start();
                osc2.start();

                let highPitch = false;
                sirenTimer = setInterval(() => {
                    if (!audioCtx || audioCtx.state === 'closed') return;
                    const freq = highPitch ? 1250 : 750;
                    osc1.frequency.setTargetAtTime(freq, audioCtx.currentTime, 0.08);
                    osc2.frequency.setTargetAtTime(freq + 40, audioCtx.currentTime, 0.08);
                    highPitch = !highPitch;
                }, 320);

                const strobe = document.getElementById('strobe-overlay');
                if (strobe) {
                    strobe.classList.remove('hidden');
                    let flip = false;
                    strobeTimer = setInterval(() => {
                        strobe.style.backgroundColor = flip ? 'rgba(239, 68, 68, 0.85)' : 'rgba(255, 255, 255, 0.92)';
                        flip = !flip;
                    }, 90);
                }

                isSirenActive = true;
                const btn = document.getElementById('siren-btn');
                if (btn) {
                    btn.textContent = '⏹️ STOP SIREN';
                    btn.className = 'px-4 py-2.5 rounded-xl font-extrabold text-xs bg-slate-900 text-white hover:bg-slate-800 transition shadow-md shrink-0';
                }
            } catch(e) {
                console.error('AudioContext start error:', e);
                alert('Audio Siren error: ' + e.message);
            }
        }

        function stopSiren() {
            isSirenActive = false;
            if (sirenTimer) clearInterval(sirenTimer);
            if (strobeTimer) clearInterval(strobeTimer);

            if (gainNode && audioCtx) {
                gainNode.gain.setTargetAtTime(0, audioCtx.currentTime, 0.05);
                setTimeout(() => {
                    try {
                        if (osc1) osc1.stop();
                        if (osc2) osc2.stop();
                        if (audioCtx) audioCtx.close();
                    } catch(e){}
                }, 100);
            }

            const strobe = document.getElementById('strobe-overlay');
            if (strobe) strobe.classList.add('hidden');

            const btn = document.getElementById('siren-btn');
            if (btn) {
                btn.textContent = '🚨 START SIREN';
                btn.className = 'px-4 py-2.5 rounded-xl font-extrabold text-xs bg-white text-red-700 hover:bg-red-50 transition shadow-md shrink-0';
            }
        }
    </script>
    @endpush
</x-app-layout>
