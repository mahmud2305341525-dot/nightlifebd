<x-app-layout>
    <x-slot name="header">
        <h1 class="font-extrabold text-xl flex items-center gap-2 text-slate-800 dark:text-slate-100">⏱️ {{ __('Walk With Me — Safety Timer') }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Set a countdown. If you don\'t tap "I am safe" in time, your emergency contacts are alerted automatically.') }}</p>
    </x-slot>

    <div class="max-w-2xl mx-auto p-4 space-y-5">

        @unless ($hasContacts)
            <div class="rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 p-4 text-sm text-amber-900 dark:text-amber-200 flex gap-3 items-center">
                ⚠️ {{ __('Add an emergency contact first so the timer can alert someone.') }}
                <a class="underline font-semibold ml-1 text-amber-700 dark:text-amber-400" href="{{ route('contacts.index') }}">{{ __('Add contacts') }}</a>
            </div>
        @endunless

        {{-- Live Countdown Widget --}}
        @php $running = $timers->firstWhere(fn($t) => $t->status() === 'running'); @endphp
        @if ($running)
            <div id="wwm-widget" class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl shadow-xl p-6 space-y-4 border border-slate-700">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-xs uppercase tracking-widest text-slate-400 mb-1">{{ __('Walk With Me — Active') }}</div>
                        <div class="font-bold text-lg">{{ $running->note ?: __('Safety timer running…') }}</div>
                        <div class="text-sm text-slate-400">{{ __('Expires at') }}: {{ $running->due_at->format('h:i A') }}</div>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-0 rounded-full bg-green-500 opacity-30 animate-ping"></span>
                        <span class="relative flex h-4 w-4 rounded-full bg-green-400 mt-1"></span>
                    </div>
                </div>

                {{-- Countdown ring --}}
                <div class="flex flex-col items-center py-4">
                    <div class="relative" style="width:160px;height:160px">
                        <svg class="absolute inset-0 w-full h-full -rotate-90" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="54" fill="none" stroke="#374151" stroke-width="8"/>
                            <circle id="wwm-ring" cx="60" cy="60" r="54" fill="none" stroke="#22c55e" stroke-width="8"
                                stroke-dasharray="339.3" stroke-dashoffset="0"
                                stroke-linecap="round" style="transition:stroke-dashoffset 1s linear,stroke 1s"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <div id="wwm-digits" class="text-3xl font-extrabold tabular-nums tracking-tight">00:00:00</div>
                            <div class="text-xs text-slate-400">{{ __('remaining') }}</div>
                        </div>
                    </div>
                </div>

                {{-- I am safe button --}}
                <form method="POST" action="{{ route('timer.confirm', $running) }}">
                    @csrf
                    <button type="submit"
                        class="w-full bg-green-500 hover:bg-green-400 active:scale-[.98] text-white font-extrabold text-lg py-4 rounded-xl shadow-lg transition-all select-none">
                        ✅ {{ __('I am safe — Cancel timer') }}
                    </button>
                </form>
                <p class="text-xs text-center text-slate-400">{{ __('If you do not press this button before the countdown reaches zero, your contacts will be alerted.') }}</p>
            </div>
        @endif

        {{-- Start New Timer --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5">
            <h2 class="font-bold mb-3 text-slate-900 dark:text-slate-100">⚙️ {{ __('Start a new timer') }}</h2>
            <form method="POST" action="{{ route('timer.store') }}" class="space-y-3 text-sm">
                @csrf
                <div>
                    <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wide">{{ __('Quick presets') }}</div>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ([10 => '10 min', 20 => '20 min', 30 => '30 min', 60 => '1 hr'] as $m => $label)
                            <button type="button" data-m="{{ $m }}"
                                class="quick border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 hover:border-indigo-400 rounded-lg py-2 text-center font-semibold transition-all text-xs">
                                +{{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <label class="block font-semibold text-slate-800 dark:text-slate-200">{{ __('I will arrive safely by') }}
                    <input type="datetime-local" id="due_at" name="due_at" value="{{ old('due_at') }}"
                        class="mt-1 w-full border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg focus:ring-indigo-400" required>
                </label>

                <input name="note" maxlength="120" placeholder="{{ __('e.g. Going home from Gulshan 2 by CNG') }}"
                    value="{{ old('note') }}" class="w-full border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg focus:ring-indigo-400">

                <x-input-error :messages="$errors->all()" />
                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl py-3 font-bold shadow-sm transition-all">
                    {{ __('Start Walk With Me timer') }}
                </button>
            </form>
        </div>

        {{-- How it works --}}
        <div class="bg-gradient-to-br from-indigo-50 to-blue-50 dark:from-indigo-950/40 dark:to-blue-950/30 rounded-xl border border-indigo-100 dark:border-indigo-800/60 p-5">
            <h2 class="font-bold mb-3 text-indigo-900 dark:text-indigo-200">{{ __('How it works') }}</h2>
            <div class="space-y-3">
                @foreach ([
                    ['1', 'bg-indigo-600', __('Set a deadline'), __('Pick when you expect to arrive home or at your destination.')],
                    ['2', 'bg-blue-600', __('Walk safely'), __('Keep your phone on you. The countdown runs in the background.')],
                    ['3', 'bg-green-600', __('Tap "I am safe"'), __('Before the timer hits zero, confirm you arrived safely.')],
                    ['4', 'bg-red-600', __('Auto-SOS if missed'), __('If you don\'t confirm in time, your contacts are instantly alerted.')],
                ] as [$num, $cls, $title, $desc])
                    <div class="flex gap-3 items-start">
                        <span class="flex-shrink-0 w-7 h-7 {{ $cls }} text-white rounded-full flex items-center justify-center text-sm font-bold shadow-sm">{{ $num }}</span>
                        <div>
                            <div class="font-semibold text-sm text-slate-800 dark:text-slate-100">{{ $title }}</div>
                            <div class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">{{ $desc }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Past timers --}}
        @if ($timers->isNotEmpty())
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4">
            <h2 class="font-bold mb-3 text-sm text-slate-900 dark:text-slate-100">{{ __('Recent timers') }}</h2>
            <div class="space-y-2">
                @foreach ($timers as $t)
                    @php $st = $t->status(); @endphp
                    <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-800 last:border-0 text-sm gap-3 text-slate-800 dark:text-slate-200">
                        <div class="min-w-0">
                            <div class="font-semibold truncate">{{ $t->due_at->format('d M, h:i A') }}
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{
                                    ['running' => 'bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300',
                                     'overdue' => 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300',
                                     'alerted' => 'bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300',
                                     'confirmed' => 'bg-green-100 dark:bg-green-950/60 text-green-800 dark:text-green-300'][$st]
                                }}">{{ __($st) }}</span>
                            </div>
                            @if ($t->note)<div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $t->note }}</div>@endif
                        </div>
                        <div class="flex-shrink-0">
                            @if (in_array($st, ['running', 'overdue']))
                                <form method="POST" action="{{ route('timer.confirm', $t) }}">@csrf
                                    <button class="bg-green-600 hover:bg-green-500 text-white rounded-lg px-3 py-1.5 text-xs font-bold transition-all">✔ {{ __('Safe') }}</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('timer.destroy', $t) }}">@csrf @method('DELETE')
                                    <button class="text-xs text-slate-400 hover:text-red-500 dark:hover:text-red-400 underline">{{ __('Remove') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('trips.index') }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-3 text-center hover:shadow-md transition text-sm text-slate-800 dark:text-slate-200 block">
                <div class="text-2xl mb-1">📍</div><div class="font-semibold">{{ __('Live trip') }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('Share GPS with contacts') }}</div>
            </a>
            <a href="{{ route('sos.index') }}" class="bg-red-50 dark:bg-red-950/40 rounded-xl shadow-sm border border-red-100 dark:border-red-900/60 p-3 text-center hover:shadow-md transition text-sm block">
                <div class="text-2xl mb-1">🆘</div><div class="font-semibold text-red-700 dark:text-red-300">{{ __('SOS') }}</div>
                <div class="text-xs text-red-400 dark:text-red-300/80">{{ __('Alert contacts now') }}</div>
            </a>
        </div>

        <p class="text-xs text-slate-400 dark:text-slate-500 text-center">{{ __('Alerts are checked every minute by the scheduler (php artisan schedule:work).') }}</p>
    </div>

    @push('scripts')
    <script>
        const pad = n => String(n).padStart(2, '0');
        document.querySelectorAll('.quick').forEach(b => b.onclick = () => {
            const d = new Date(Date.now() + parseInt(b.dataset.m, 10) * 60000);
            document.getElementById('due_at').value =
                `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            document.querySelectorAll('.quick').forEach(x => x.classList.remove('bg-indigo-50','border-indigo-400'));
            b.classList.add('bg-indigo-50','border-indigo-400');
        });

        @if ($running ?? false)
        // Live countdown ring for active timer
        const dueTs   = {{ $running->due_at->timestamp }};
        const startTs = {{ $running->created_at->timestamp }};
        const totalSec = Math.max(1, dueTs - startTs);
        const ring   = document.getElementById('wwm-ring');
        const digits = document.getElementById('wwm-digits');
        const CIRCUM = 339.3;

        function tickCountdown() {
            const rem = Math.max(0, dueTs - Math.floor(Date.now() / 1000));
            digits.textContent = `${pad(Math.floor(rem/3600))}:${pad(Math.floor(rem%3600/60))}:${pad(rem%60)}`;

            const frac = rem / totalSec;
            ring.style.strokeDashoffset = CIRCUM * (1 - frac);
            ring.style.stroke = frac > 0.5 ? '#22c55e' : frac > 0.25 ? '#f59e0b' : '#ef4444';

            if (rem <= 0) {
                ring.style.strokeDashoffset = 0;
                ring.style.stroke = '#ef4444';
                document.getElementById('wwm-widget').classList.add('ring-4','ring-red-500','animate-pulse');
                clearInterval(countdownInterval);
            }
        }
        tickCountdown();
        const countdownInterval = setInterval(tickCountdown, 1000);
        @endif
    </script>
    @endpush
</x-app-layout>
