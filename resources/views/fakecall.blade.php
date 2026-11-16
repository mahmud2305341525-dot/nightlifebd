<x-app-layout>
    <div class="max-w-md mx-auto p-4 space-y-3 text-sm">
        <div class="rounded-xl bg-amber-50 border border-amber-300 p-3.5 text-xs text-amber-900 space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-amber-950 text-sm">
                <span>⚠️</span> <span>{{ __('Simulated Call Only') }}</span>
            </div>
            <p>{{ __('This tool produces an audio ring and visual call screen on this device to help you exit uncomfortable situations politely. It DOES NOT dial real phone networks, does not contact 999, and does not notify emergency contacts. In an actual emergency, call 999 immediately.') }}</p>
        </div>

        <h1 class="text-xl font-bold">📞 {{ __('Fake incoming call') }}</h1>
        <p class="text-slate-600">{{ __('Use it to leave an uncomfortable situation politely. Choose a caller name and a delay, then lock the screen as if it is a normal call.') }}</p>
        <input id="caller" value="Ammu" class="w-full border-slate-300 rounded-md" placeholder="{{ __('Caller name') }}">
        <select id="delay" class="w-full border-slate-300 rounded-md">
            <option value="0">{{ __('Ring now') }}</option><option value="10">{{ __('In 10 seconds') }}</option><option value="30">{{ __('In 30 seconds') }}</option><option value="60">{{ __('In 1 minute') }}</option>
        </select>
        <button id="start" class="w-full bg-slate-900 text-white rounded-md py-2.5 font-semibold">{{ __('Start') }}</button>
    </div>

    <div id="call" class="hidden fixed inset-0 z-[70] bg-slate-900 text-white flex flex-col items-center justify-between py-16">
        <div class="text-center">
            <div id="c-state" class="text-slate-400">{{ __('Incoming call') }}...</div>
            <div id="c-name" class="text-4xl font-semibold mt-3"></div>
            <div class="text-slate-400 mt-1">mobile</div>
            <div id="c-time" class="text-2xl mt-6 hidden">00:00</div>
        </div>
        <div class="flex gap-16">
            <button id="decline" class="w-16 h-16 rounded-full bg-red-600 text-2xl">✖</button>
            <button id="accept" class="w-16 h-16 rounded-full bg-green-600 text-2xl">📞</button>
        </div>
    </div>

    @push('scripts')
        <script>
            const $ = id => document.getElementById(id);
            let ctx = null, ring = null, secs = 0, tm = null;
            function startRing() {
                try {
                    ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const beep = () => { const o = ctx.createOscillator(), g = ctx.createGain(); o.frequency.value = 440; g.gain.value = .15; o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + .6); };
                    beep(); ring = setInterval(() => { beep(); if (navigator.vibrate) navigator.vibrate([400, 200, 400]); }, 1800);
                } catch (e) {}
            }
            function stopRing() { clearInterval(ring); if (ctx) ctx.close(); ctx = null; }
            function end() { stopRing(); clearInterval(tm); $('call').classList.add('hidden'); secs = 0; $('c-time').classList.add('hidden'); $('c-state').textContent = '{{ __('Incoming call') }}...'; }
            $('start').onclick = () => {
                const name = $('caller').value || 'Ammu', d = parseInt($('delay').value, 10) * 1000;
                // unlock audio on user gesture
                try { new (window.AudioContext || window.webkitAudioContext)().resume(); } catch (e) {}
                setTimeout(() => { $('c-name').textContent = name; $('call').classList.remove('hidden'); startRing(); }, d);
                if (d) $('start').textContent = @json(__('Armed - ringing soon'));
            };
            $('accept').onclick = () => {
                stopRing(); $('c-state').textContent = @json(__('Call in progress')); $('c-time').classList.remove('hidden');
                tm = setInterval(() => { secs++; $('c-time').textContent = String(Math.floor(secs / 60)).padStart(2, '0') + ':' + String(secs % 60).padStart(2, '0'); }, 1000);
            };
            $('decline').onclick = end;
        </script>
    @endpush
</x-app-layout>
