@php
    $main = [
        ['venues.index', __('Explore'), 'venues.*'],
        ['route.index', __('Safe Route'), 'route.*'],
        ['heatmap.index', __('Heatmap'), 'heatmap.*'],
        ['planner.index', __('Night Planner'), 'planner.*'],
        ['near-me', __('Near Me'), 'near-me'],
    ];
    $tools = [
        ['reports.create', __('Report an incident / review')],
        ['reports.mine', __('My reports')],
        ['trips.index', __('Live trip & ride check-in')],
        ['timer.index', __('Safety timer')],
        ['fakecall', __('Fake call')],
        ['contacts.index', __('Emergency contacts')],
        ['sos.index', 'SOS'],
    ];
    $linkClass = fn ($active) => 'px-3 py-2 rounded-md text-sm font-medium transition '.($active ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-300' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800');
@endphp
<nav x-data="{ open: false }" class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14 items-center">
            <div class="flex items-center gap-2">
                <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="flex items-center gap-2 font-bold text-slate-900 dark:text-white me-3">
                    <x-application-logo class="h-7 w-7" />
                    <span>Night<span class="text-indigo-600 dark:text-indigo-400">Life</span>BD</span>
                </a>
                <div class="hidden lg:flex items-center gap-1">
                    @foreach ($main as [$r, $label, $pattern])
                        <a href="{{ route($r) }}" class="{{ $linkClass(request()->routeIs($pattern)) }}">{{ $label }}</a>
                    @endforeach
                    @if (config('nightlife.demo_enabled', false))
                        <a href="{{ route('demo.index') }}" class="px-2.5 py-1 rounded-md text-xs font-bold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700 hover:bg-amber-200 dark:hover:bg-amber-900 transition flex items-center gap-1">
                            <span>⚡</span> <span>Demo Hub</span>
                        </a>
                    @endif
                </div>
            </div>

            <div class="hidden lg:flex items-center gap-2">
                {{-- Night Vision Mode Toggle --}}
                <button type="button" onclick="toggleNightMode()" id="night-toggle"
                    class="p-1.5 rounded-md border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition text-xs flex items-center gap-1 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    title="{{ __('Toggle Night Vision Mode') }}" aria-label="{{ __('Toggle Night Vision Mode') }}">
                    <span class="night-icon" id="night-icon">🌙</span>
                </button>

                <div class="text-xs border border-slate-300 dark:border-slate-700 rounded-md overflow-hidden flex">
                    <a href="{{ route('lang', 'en') }}" class="px-2 py-1 {{ app()->getLocale() === 'en' ? 'bg-slate-900 dark:bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">EN</a>
                    <a href="{{ route('lang', 'bn') }}" class="px-2 py-1 {{ app()->getLocale() === 'bn' ? 'bg-slate-900 dark:bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">বাং</a>
                </div>

                @auth
                    <x-dropdown align="right" width="w-64">
                        <x-slot name="trigger">
                            <button class="{{ $linkClass(false) }}">{{ __('Safety tools') }} &#9662;</button>
                        </x-slot>
                        <x-slot name="content">
                            @foreach ($tools as [$r, $label])
                                <x-dropdown-link :href="route($r)">{{ $label }}</x-dropdown-link>
                            @endforeach
                        </x-slot>
                    </x-dropdown>

                    @hasanyrole('venue_partner|safety_admin|super_admin')
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="{{ $linkClass(false) }}">{{ __('Manage') }} &#9662;</button>
                            </x-slot>
                            <x-slot name="content">
                                @hasanyrole('venue_partner|super_admin')
                                    <x-dropdown-link :href="route('partner.venues.index')">{{ __('My venues') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('partner.responses.index')">{{ __('Right-of-Reply') }}</x-dropdown-link>
                                @endhasanyrole
                            @hasanyrole('safety_admin|super_admin')
                                    <x-dropdown-link :href="route('admin.dashboard')">{{ __('Analytics dashboard') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.reports')">{{ __('Moderate reports') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.flags.index')">{{ __('Abuse & Flags') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.venues')">{{ __('Approve venues') }}</x-dropdown-link>
                                @endhasanyrole
                                @role('super_admin')
                                    <x-dropdown-link :href="route('admin.users')">{{ __('Users') }}</x-dropdown-link>
                                @endrole
                            </x-slot>
                        </x-dropdown>
                    @endhasanyrole

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="{{ $linkClass(false) }}">{{ Auth::user()->name }} &#9662;</button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link :href="route('dashboard')">{{ __('Dashboard') }}</x-dropdown-link>
                            <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('Log Out') }}</x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <a href="{{ route('login') }}" class="{{ $linkClass(false) }}">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="px-3 py-2 rounded-md text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-500">{{ __('Register') }}</a>
                @endauth
            </div>

            <button @click="open = ! open" class="lg:hidden p-2 rounded-md text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Menu">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak class="lg:hidden border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3 space-y-1">
        @foreach ($main as [$r, $label, $pattern])
            <a href="{{ route($r) }}" class="block {{ $linkClass(request()->routeIs($pattern)) }}">{{ $label }}</a>
        @endforeach
        @if (config('nightlife.demo_enabled', false))
            <a href="{{ route('demo.index') }}" class="block px-3 py-2 rounded-md text-sm font-bold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700">
                ⚡ Demo Hub (Simulator)
            </a>
        @endif
        @auth
            <div class="pt-2 mt-2 border-t border-slate-100 dark:border-slate-800 text-xs uppercase text-slate-400 px-3">{{ __('Safety tools') }}</div>
            @foreach ($tools as [$r, $label])
                <a href="{{ route($r) }}" class="block {{ $linkClass(false) }}">{{ $label }}</a>
            @endforeach
            @hasanyrole('venue_partner|super_admin')
                <a href="{{ route('partner.venues.index') }}" class="block {{ $linkClass(false) }}">{{ __('My venues') }}</a>
                <a href="{{ route('partner.responses.index') }}" class="block {{ $linkClass(false) }}">{{ __('Right-of-Reply') }}</a>
            @endhasanyrole
            @hasanyrole('safety_admin|super_admin')
                <a href="{{ route('admin.dashboard') }}" class="block {{ $linkClass(false) }}">{{ __('Analytics dashboard') }}</a>
                <a href="{{ route('admin.reports') }}" class="block {{ $linkClass(false) }}">{{ __('Moderate reports') }}</a>
                <a href="{{ route('admin.flags.index') }}" class="block {{ $linkClass(false) }}">{{ __('Abuse & Flags') }}</a>
                <a href="{{ route('admin.venues') }}" class="block {{ $linkClass(false) }}">{{ __('Approve venues') }}</a>
            @endhasanyrole
            @role('super_admin')
                <a href="{{ route('admin.users') }}" class="block {{ $linkClass(false) }}">{{ __('Users') }}</a>
            @endrole
            <a href="{{ route('profile.edit') }}" class="block {{ $linkClass(false) }}">{{ __('Profile') }}</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="block w-full text-left {{ $linkClass(false) }}">{{ __('Log Out') }}</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="block {{ $linkClass(false) }}">{{ __('Log in') }}</a>
            <a href="{{ route('register') }}" class="block {{ $linkClass(false) }}">{{ __('Register') }}</a>
        @endauth
        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
            <button type="button" onclick="toggleNightMode()" class="px-2.5 py-1.5 rounded-md border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-1.5 font-medium transition cursor-pointer">
                <span class="night-icon">🌙</span>
                <span>{{ __('Toggle Night Mode') }}</span>
            </button>
            <div class="flex gap-1">
                <a href="{{ route('lang', 'en') }}" class="px-2.5 py-1 border border-slate-300 dark:border-slate-700 rounded {{ app()->getLocale() === 'en' ? 'bg-slate-900 dark:bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300' }}">English</a>
                <a href="{{ route('lang', 'bn') }}" class="px-2.5 py-1 border border-slate-300 dark:border-slate-700 rounded {{ app()->getLocale() === 'bn' ? 'bg-slate-900 dark:bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-300' }}">বাংলা</a>
            </div>
        </div>
    </div>
</nav>
