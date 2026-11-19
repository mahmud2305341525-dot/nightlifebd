<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.events.index') }}" class="p-2 rounded-lg border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 transition text-sm">
                ←
            </a>
            <div>
                <h2 class="font-bold text-xl text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span>📷</span>
                    <span>{{ __('Event Pass Scanner & Gate Check-In') }}</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Scan attendee QR codes or enter booking references to validate admission passes in real time.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Verification Box --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
            
            <div class="text-center space-y-2">
                <span class="text-4xl block">🎟️</span>
                <h3 class="font-bold text-base text-slate-900 dark:text-slate-100">
                    {{ __('Scan or Enter Pass Code') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Enter the 64-char QR Token or the 10-char Booking Reference (e.g., NL-EVT-XXXXXX)') }}
                </p>
            </div>

            <form id="checkin-form" onsubmit="event.preventDefault(); submitCode();" class="space-y-4">
                <div class="relative">
                    <input type="text" id="pass-code" placeholder="Scan QR or type NL-EVT-XXXXXX…" autofocus
                           class="w-full text-center text-sm font-mono tracking-wider py-3 px-4 rounded-2xl border-2 border-indigo-300 dark:border-indigo-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-600 focus:ring-0">
                </div>

                <div class="flex gap-2">
                    <button type="submit" id="btn-validate" class="flex-1 py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md transition flex items-center justify-center gap-2">
                        <span>🔍</span>
                        <span>{{ __('Verify & Check-In') }}</span>
                    </button>
                    <button type="button" onclick="document.getElementById('pass-code').value=''; resetResult();" class="px-4 py-3 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                        {{ __('Reset') }}
                    </button>
                </div>
            </form>

            {{-- Live Validation Result Alert Box --}}
            <div id="result-box" class="hidden p-5 rounded-2xl border-2 transition-all duration-200 space-y-3">
                <div class="flex items-center gap-3">
                    <span id="result-icon" class="text-3xl"></span>
                    <div>
                        <h4 id="result-title" class="font-extrabold text-sm"></h4>
                        <p id="result-msg" class="text-xs mt-0.5"></p>
                    </div>
                </div>

                <div id="result-details" class="p-3 rounded-xl bg-white/60 dark:bg-black/30 border border-slate-200 dark:border-slate-800 text-xs space-y-1">
                    <div><strong>{{ __('Attendee') }}:</strong> <span id="res-attendee"></span></div>
                    <div><strong>{{ __('Event') }}:</strong> <span id="res-event"></span></div>
                    <div><strong>{{ __('Tier') }}:</strong> <span id="res-tier"></span> (<span id="res-qty"></span>)</div>
                    <div><strong>{{ __('Checked-In At') }}:</strong> <span id="res-time"></span></div>
                </div>
            </div>

        </div>

    </div>

    @push('scripts')
    <script>
        async function submitCode() {
            const input = document.getElementById('pass-code');
            const code = input.value.trim();
            if (!code) return;

            const box = document.getElementById('result-box');
            const btn = document.getElementById('btn-validate');
            btn.disabled = true;
            btn.innerHTML = '<span>⏳</span> <span>{{ __('Verifying…') }}</span>';

            try {
                const res = await fetch('{{ route('admin.events.checkin') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ code: code })
                });

                const data = await res.json();
                box.classList.remove('hidden');

                if (res.ok && data.success) {
                    box.className = 'p-5 rounded-2xl border-2 border-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-900 dark:text-emerald-200 space-y-3';
                    document.getElementById('result-icon').textContent = '✅';
                    document.getElementById('result-title').textContent = '{{ __('VALID TICKET - ADMISSION GRANTED') }}';
                    document.getElementById('result-msg').textContent = data.message;
                    
                    document.getElementById('result-details').classList.remove('hidden');
                    document.getElementById('res-attendee').textContent = data.booking.attendee_name;
                    document.getElementById('res-event').textContent = data.booking.event_title;
                    document.getElementById('res-tier').textContent = data.booking.tier_name;
                    document.getElementById('res-qty').textContent = data.booking.quantity + ' pass(es)';
                    document.getElementById('res-time').textContent = data.booking.checked_in_at;
                } else {
                    box.className = 'p-5 rounded-2xl border-2 border-red-400 bg-red-50 dark:bg-red-950/50 text-red-900 dark:text-red-200 space-y-3';
                    document.getElementById('result-icon').textContent = '❌';
                    document.getElementById('result-title').textContent = '{{ __('ENTRY DENIED') }}';
                    document.getElementById('result-msg').textContent = data.message || '{{ __('Invalid ticket code') }}';
                    document.getElementById('result-details').classList.add('hidden');
                }
            } catch (err) {
                box.classList.remove('hidden');
                box.className = 'p-5 rounded-2xl border-2 border-red-400 bg-red-50 dark:bg-red-950/50 text-red-900 dark:text-red-200 space-y-3';
                document.getElementById('result-icon').textContent = '⚠️';
                document.getElementById('result-title').textContent = '{{ __('Verification Failed') }}';
                document.getElementById('result-msg').textContent = 'Network or server error.';
                document.getElementById('result-details').classList.add('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span>🔍</span> <span>{{ __('Verify & Check-In') }}</span>';
                input.select();
            }
        }

        function resetResult() {
            document.getElementById('result-box').classList.add('hidden');
            document.getElementById('pass-code').focus();
        }
    </script>
    @endpush
</x-app-layout>
