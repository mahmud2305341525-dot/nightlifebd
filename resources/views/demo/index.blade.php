<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-4">
            <div>
                <h1 class="font-extrabold text-2xl text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span>⚡ Capstone Presentation Simulator</span>
                    <span class="text-xs bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700 font-bold px-2.5 py-0.5 rounded-full uppercase">Demo Mode</span>
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">SE331 Software Engineering Design Capstone · Team Error 404 (DIU Fall 2026)</p>
            </div>

            <form method="POST" action="{{ route('demo.reset') }}" onsubmit="return confirm('Cleanly reset all demo trips, timers, dark spots and recalculate scores?')">
                @csrf
                <button type="submit" class="bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 text-white font-semibold text-xs px-4 py-2 rounded-xl border border-slate-700 transition flex items-center gap-2 shadow-sm">
                    <span>🔄</span> <span>Reset All Demo State</span>
                </button>
            </form>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto p-4 space-y-6">

        {{-- Current Session Status Banner --}}
        <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-2xl p-5 shadow-lg border border-slate-800 flex flex-wrap justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-2xl">
                    @auth 👤 @else 🚪 @endauth
                </div>
                <div>
                    <div class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Active Session</div>
                    <div class="text-lg font-bold">
                        @auth
                            {{ auth()->user()->name }} <span class="text-xs text-amber-400 font-normal">({{ auth()->user()->email }})</span>
                            <span class="ml-2 text-xs bg-indigo-500/30 text-indigo-300 border border-indigo-400/30 px-2 py-0.5 rounded-full font-semibold">
                                Role: {{ auth()->user()->roles->first()?->name ?? 'user' }}
                            </span>
                        @else
                            Guest (Not Logged In)
                        @endauth
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-6 text-xs text-slate-300 border-t sm:border-t-0 sm:border-l border-slate-800 pt-3 sm:pt-0 sm:pl-6">
                <div>
                    <div class="text-slate-400">Approved Venues</div>
                    <div class="font-bold text-white text-base">{{ $venueCount }}</div>
                </div>
                <div>
                    <div class="text-slate-400">Verified Reports</div>
                    <div class="font-bold text-white text-base">{{ $reportCount }}</div>
                </div>
                <div>
                    <div class="text-slate-400">Active Timers</div>
                    <div class="font-bold text-white text-base">{{ $timerCount }}</div>
                </div>
            </div>
        </div>

        @guest
            <div class="rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 p-4 text-sm text-amber-900 dark:text-amber-200 flex flex-wrap items-center justify-between gap-3 shadow-sm">
                <div>
                    <span class="font-bold">🔒 {{ __('Authentication Required:') }}</span>
                    <span>{{ __('To switch personas or run live scenario simulations, log in as any test user (e.g. user@test.com / password).') }}</span>
                </div>
                <a href="{{ route('login') }}" class="bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs px-3.5 py-1.5 rounded-lg transition shadow-sm">{{ __('Log In to Demo') }}</a>
            </div>
        @endguest

        {{-- SECTION 1: 1-Click Role Switcher --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800">
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-1 flex items-center gap-2">
                <span>👥</span> <span>1-Click Role Switcher (Instant Authentication)</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Switch instantly between the 4 stakeholder roles during your faculty presentation without typing credentials.</p>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {{-- Role: Regular User --}}
                <div class="border rounded-xl p-4 {{ auth()->user()?->email === 'user@test.com' ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/40 ring-2 ring-indigo-200 dark:ring-indigo-800' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 hover:border-slate-300 dark:hover:border-slate-600' }} transition">
                    <div class="text-2xl mb-1">👤</div>
                    <div class="font-bold text-sm text-slate-900 dark:text-slate-100">Regular Citizen</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-3">user@test.com</div>
                    <p class="text-[11px] text-slate-600 dark:text-slate-300 mb-4">Can explore venues, plan night-outs, start trips, report incidents, and trigger SOS.</p>
                    <form method="POST" action="{{ route('demo.switch', 'user') }}">
                        @csrf
                        <button type="submit" class="w-full py-2 px-3 rounded-lg text-xs font-semibold {{ auth()->user()?->email === 'user@test.com' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200' }} transition">
                            {{ auth()->user()?->email === 'user@test.com' ? '● Active Role' : 'Login as Citizen' }}
                        </button>
                    </form>
                </div>

                {{-- Role: Safety Admin --}}
                <div class="border rounded-xl p-4 {{ auth()->user()?->email === 'safety@test.com' ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/40 ring-2 ring-indigo-200 dark:ring-indigo-800' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 hover:border-slate-300 dark:hover:border-slate-600' }} transition">
                    <div class="text-2xl mb-1">🛡️</div>
                    <div class="font-bold text-sm text-slate-900 dark:text-slate-100">Safety Moderator</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-3">safety@test.com</div>
                    <p class="text-[11px] text-slate-600 dark:text-slate-300 mb-4">Reviews the AI-triaged incident queue, verifies/rejects reports, and inspects duplicate clusters.</p>
                    <form method="POST" action="{{ route('demo.switch', 'safety') }}">
                        @csrf
                        <button type="submit" class="w-full py-2 px-3 rounded-lg text-xs font-semibold {{ auth()->user()?->email === 'safety@test.com' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200' }} transition">
                            {{ auth()->user()?->email === 'safety@test.com' ? '● Active Role' : 'Login as Moderator' }}
                        </button>
                    </form>
                </div>

                {{-- Role: Venue Partner --}}
                <div class="border rounded-xl p-4 {{ auth()->user()?->email === 'partner@test.com' ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/40 ring-2 ring-indigo-200 dark:ring-indigo-800' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 hover:border-slate-300 dark:hover:border-slate-600' }} transition">
                    <div class="text-2xl mb-1">🏢</div>
                    <div class="font-bold text-sm text-slate-900 dark:text-slate-100">Venue Partner</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-3">partner@test.com</div>
                    <p class="text-[11px] text-slate-600 dark:text-slate-300 mb-4">Owns venues, updates security checklists (CCTV, guards, lighting), and receives AI Safety Coach tips.</p>
                    <form method="POST" action="{{ route('demo.switch', 'partner') }}">
                        @csrf
                        <button type="submit" class="w-full py-2 px-3 rounded-lg text-xs font-semibold {{ auth()->user()?->email === 'partner@test.com' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200' }} transition">
                            {{ auth()->user()?->email === 'partner@test.com' ? '● Active Role' : 'Login as Partner' }}
                        </button>
                    </form>
                </div>

                {{-- Role: Super Admin --}}
                <div class="border rounded-xl p-4 {{ auth()->user()?->email === 'admin@test.com' ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/40 ring-2 ring-indigo-200 dark:ring-indigo-800' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 hover:border-slate-300 dark:hover:border-slate-600' }} transition">
                    <div class="text-2xl mb-1">👑</div>
                    <div class="font-bold text-sm text-slate-900 dark:text-slate-100">Super Admin</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-3">admin@test.com</div>
                    <p class="text-[11px] text-slate-600 dark:text-slate-300 mb-4">Full administrative oversight: user management, role assignments, bans, and system audit logs.</p>
                    <form method="POST" action="{{ route('demo.switch', 'admin') }}">
                        @csrf
                        <button type="submit" class="w-full py-2 px-3 rounded-lg text-xs font-semibold {{ auth()->user()?->email === 'admin@test.com' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200' }} transition">
                            {{ auth()->user()?->email === 'admin@test.com' ? '● Active Role' : 'Login as Admin' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- SECTION 2: Live Scenario Injections --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-1 flex items-center gap-2">
                    <span>🎬</span> <span>Interactive Presentation Scenarios</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Inject real-world events on demand to demonstrate algorithmic intelligence to the examiners.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-4">
                {{-- Scenario 1 --}}
                <div class="border border-red-200 dark:border-red-900/60 bg-red-50/50 dark:bg-red-950/30 rounded-xl p-4 flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center gap-2 text-red-700 dark:text-red-300 font-bold text-sm">
                            <span>🚨</span> <span>Scenario 1: Live Incident Injection</span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-2">
                            Injects a realistic Bangla incident report:
                            <span class="block italic text-[11px] text-red-900 dark:text-red-200 bg-white dark:bg-slate-900 p-2 rounded border border-red-200 dark:border-red-800 mt-1">
                                "রাত ১২:৩০ এ ধানমন্ডি লেকের পাশের রাস্তায় ছিনতাইকারীর কবলে পড়েছিলাম..."
                            </span>
                        </p>
                        <ul class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 space-y-1">
                            <li>✔ Automatically sets category to <strong>Theft</strong> & severity to <strong>4/5</strong></li>
                            <li>✔ Instantly recalibrates Dhanmondi venue safety scores</li>
                            <li>✔ Adds high-risk heat spot to the Heatmap</li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('demo.incident') }}">
                        @csrf
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-500 text-white font-semibold text-xs py-2.5 rounded-lg shadow transition flex items-center justify-center gap-2">
                            <span>⚡</span> <span>Inject Incident in Dhanmondi</span>
                        </button>
                    </form>
                </div>

                {{-- Scenario 2 --}}
                <div class="border border-amber-200 dark:border-amber-900/60 bg-amber-50/50 dark:bg-amber-950/30 rounded-xl p-4 flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center gap-2 text-amber-700 dark:text-amber-300 font-bold text-sm">
                            <span>⏱️</span> <span>Scenario 2: Safety Timer Alert</span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-2">
                            Simulates an overdue safety timer (dead-man switch) where the user hasn't checked in:
                            <span class="block italic text-[11px] text-amber-900 dark:text-amber-200 bg-white dark:bg-slate-900 p-2 rounded border border-amber-200 dark:border-amber-800 mt-1">
                                "Walking home from Dhanmondi 27 — due 5 minutes ago."
                            </span>
                        </p>
                        <ul class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 space-y-1">
                            <li>✔ Dispatches emergency notification via <code>AlertService</code></li>
                            <li>✔ Logs alert in <code>AuditLog</code> with contact recipient list</li>
                            <li>✔ Demonstrates automated protection without manual SOS trigger</li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('demo.timer') }}">
                        @csrf
                        <button type="submit" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs py-2.5 rounded-lg shadow transition flex items-center justify-center gap-2">
                            <span>⚡</span> <span>Trigger Overdue Timer Alert</span>
                        </button>
                    </form>
                </div>

                {{-- Scenario 3 --}}
                <div class="border border-purple-200 dark:border-purple-900/60 bg-purple-50/50 dark:bg-purple-950/30 rounded-xl p-4 flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center gap-2 text-purple-700 dark:text-purple-300 font-bold text-sm">
                            <span>🛡️</span> <span>Scenario 3: Spam Rejection & Laplace Trust</span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-2">
                            Simulates a malicious user submitting promotional crypto spam:
                            <span class="block italic text-[11px] text-purple-900 dark:text-purple-200 bg-white dark:bg-slate-900 p-2 rounded border border-purple-200 dark:border-purple-800 mt-1">
                                "FREE BITCOIN PRIZE CLICK HERE http://spam.example.com"
                            </span>
                        </p>
                        <ul class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 space-y-1">
                            <li>✔ Marked as rejected spam by moderator</li>
                            <li>✔ Demonstrates <code>(verified+1)/(verified+rejected+2)</code> Laplace formula</li>
                            <li>✔ User's trust score immediately drops from <strong>0.50 → 0.33</strong></li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('demo.spam') }}">
                        @csrf
                        <button type="submit" class="w-full bg-purple-600 hover:bg-purple-500 text-white font-semibold text-xs py-2.5 rounded-lg shadow transition flex items-center justify-center gap-2">
                            <span>⚡</span> <span>Simulate Spam & Drop Trust</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
