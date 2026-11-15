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
    $linkClass = fn ($active) => 'px-3 py-2 rounded-md text-sm font-medium '.($active ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100');
@endphp
<nav x-data="{ open: false }" class="bg-white border-b border-slate-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14 items-center">
            <div class="flex items-center gap-2">
                <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="flex items-center gap-2 font-bold text-slate-900 me-3">
                    <x-application-logo class="h-7 w-7" />
                    <span>Night<span class="text-indigo-600">Life</span>BD</span>
                </a>
                <div class="hidden lg:flex items-center gap-1">
                    @foreach ($main as [$r, $label, $pattern])
                        <a href="{{ route($r) }}" class="{{ $linkClass(request()->routeIs($pattern)) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <div class="hidden lg:flex items-center gap-2">
                <div class="text-xs border border-slate-300 rounded-md overflow-hidden flex">
                    <a href="{{ route('lang', 'en') }}" class="px-2 py-1 {{ app()->getLocale() === 'en' ? 'bg-slate-900 text-white' : 'text-slate-600' }}">EN</a>
                    <a href="{{ route('lang', 'bn') }}" class="px-2 py-1 {{ app()->getLocale() === 'bn' ? 'bg-slate-900 text-white' : 'text-slate-600' }}">বাং</a>
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
                                @endhasanyrole
                                @hasanyrole('safety_admin|super_admin')
                                    <x-dropdown-link :href="route('admin.reports')">{{ __('Moderate reports') }}</x-dropdown-link>
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

            <button @click="open = ! open" class="lg:hidden p-2 rounded-md text-slate-500 hover:bg-slate-100" aria-label="Menu">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak class="lg:hidden border-t border-slate-200 px-4 py-3 space-y-1">
        @foreach ($main as [$r, $label, $pattern])
            <a href="{{ route($r) }}" class="block {{ $linkClass(request()->routeIs($pattern)) }}">{{ $label }}</a>
        @endforeach
        @auth
            <div class="pt-2 mt-2 border-t border-slate-100 text-xs uppercase text-slate-400 px-3">{{ __('Safety tools') }}</div>
            @foreach ($tools as [$r, $label])
                <a href="{{ route($r) }}" class="block {{ $linkClass(false) }}">{{ $label }}</a>
            @endforeach
            @hasanyrole('venue_partner|super_admin')
                <a href="{{ route('partner.venues.index') }}" class="block {{ $linkClass(false) }}">{{ __('My venues') }}</a>
            @endhasanyrole
            @hasanyrole('safety_admin|super_admin')
                <a href="{{ route('admin.reports') }}" class="block {{ $linkClass(false) }}">{{ __('Moderate reports') }}</a>
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
        <div class="pt-2 flex gap-2 text-xs">
            <a href="{{ route('lang', 'en') }}" class="px-3 py-1 border rounded {{ app()->getLocale() === 'en' ? 'bg-slate-900 text-white' : '' }}">English</a>
            <a href="{{ route('lang', 'bn') }}" class="px-3 py-1 border rounded {{ app()->getLocale() === 'bn' ? 'bg-slate-900 text-white' : '' }}">বাংলা</a>
        </div>
    </div>
</nav>
