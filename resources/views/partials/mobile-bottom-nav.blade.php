<div class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 shadow-lg px-2 py-1">
    <div class="grid grid-cols-5 items-center justify-around text-center">
        {{-- Explore --}}
        <a href="{{ route('venues.index') }}" class="flex flex-col items-center py-1.5 text-[10px] {{ request()->routeIs('venues.*') ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
            <span class="text-lg">🏢</span>
            <span>{{ __('Explore') }}</span>
        </a>

        {{-- Safe Route --}}
        <a href="{{ route('route.index') }}" class="flex flex-col items-center py-1.5 text-[10px] {{ request()->routeIs('route.*') ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
            <span class="text-lg">🛣️</span>
            <span>{{ __('Route') }}</span>
        </a>

        {{-- SOS Emergency (Prominent Center) --}}
        <a href="{{ route('sos.index') }}" class="flex flex-col items-center py-1 text-[10px] text-red-600 dark:text-red-400 font-bold -mt-3">
            <span class="w-10 h-10 rounded-full bg-red-600 hover:bg-red-700 text-white flex items-center justify-center text-lg shadow-md ring-4 ring-white dark:ring-slate-900">
                🆘
            </span>
            <span class="mt-0.5">{{ __('SOS') }}</span>
        </a>

        {{-- Near Me --}}
        <a href="{{ route('near-me') }}" class="flex flex-col items-center py-1.5 text-[10px] {{ request()->routeIs('near-me') ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
            <span class="text-lg">🏥</span>
            <span>{{ __('Near Me') }}</span>
        </a>

        {{-- Profile / Account --}}
        @auth
            <a href="{{ route('profile.edit') }}" class="flex flex-col items-center py-1.5 text-[10px] {{ request()->routeIs('profile.*') ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                <span class="text-lg">👤</span>
                <span>{{ __('Profile') }}</span>
            </a>
        @else
            <a href="{{ route('login') }}" class="flex flex-col items-center py-1.5 text-[10px] {{ request()->routeIs('login') ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400' }}">
                <span class="text-lg">🔑</span>
                <span>{{ __('Login') }}</span>
            </a>
        @endauth
    </div>
</div>
