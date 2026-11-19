<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span>🎸</span>
                    <span>{{ __('Night Events & Concerts') }}</span>
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Discover live music, gigs, and festivals in Dhaka with integrated venue safety intelligence & secure passage.') }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('events.bookings.mine') }}" class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition flex items-center gap-1.5 shadow-sm">
                        <span>🎟️</span>
                        <span>{{ __('My Event Passes') }}</span>
                    </a>
                    @if (auth()->user()->isStaff())
                        <a href="{{ route('admin.events.index') }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white transition flex items-center gap-1 shadow-sm">
                            <span>⚙️</span>
                            <span>{{ __('Manage & Scanner') }}</span>
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        {{-- Filter & Search Bar --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm p-4">
            <form method="GET" action="{{ route('events.index') }}" class="space-y-3">
                <div class="flex flex-col md:flex-row gap-3">
                    <div class="flex-1 relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            🔍
                        </span>
                        <input type="text" name="q" value="{{ $search }}"
                               placeholder="{{ __('Search by concert title, artist, or location…') }}"
                               class="w-full pl-9 pr-4 py-2 rounded-xl text-sm border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="flex gap-2">
                        <select name="area" class="rounded-xl text-sm border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('All Areas in Dhaka') }}</option>
                            @foreach ($areas as $area)
                                <option value="{{ $area }}" @selected($selectedArea === $area)>{{ $area }}</option>
                            @endforeach
                        </select>

                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold transition shrink-0">
                            {{ __('Filter') }}
                        </button>

                        @if ($search || $selectedArea || $selectedCategory)
                            <a href="{{ route('events.index') }}" class="px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 transition shrink-0 flex items-center">
                                {{ __('Clear') }}
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Category Pills --}}
                <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
                    <a href="{{ route('events.index', array_filter(['q' => $search, 'area' => $selectedArea])) }}"
                       class="px-3 py-1.5 rounded-full font-medium transition shrink-0 {{ empty($selectedCategory) ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                        {{ __('All Categories') }}
                    </a>
                    @foreach ($categories as $catKey => $catLabel)
                        <a href="{{ route('events.index', array_filter(['category' => $catKey, 'q' => $search, 'area' => $selectedArea])) }}"
                           class="px-3 py-1.5 rounded-full font-medium transition shrink-0 {{ $selectedCategory === $catKey ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                            {{ __($catLabel) }}
                        </a>
                    @endforeach
                </div>
            </form>
        </div>

        {{-- Events Grid --}}
        @if ($events->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($events as $event)
                    @php
                        $lowest = $event->lowestPrice();
                        $safetyScore = $event->getVenueSafetyScore();
                    @endphp
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden flex flex-col hover:border-indigo-400 dark:hover:border-indigo-600 transition group">
                        
                        {{-- Event Header Banner / Category --}}
                        <div class="p-5 pb-3 border-b border-slate-100 dark:border-slate-800 flex items-start justify-between gap-3">
                            <div>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/80">
                                    {{ $event->categoryLabel() }}
                                </span>
                                @if ($event->is_featured)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 dark:bg-amber-950/70 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/80 ml-1">
                                        ⭐ {{ __('Featured') }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                                📍 {{ $event->area }}
                            </span>
                        </div>

                        {{-- Event Body --}}
                        <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition leading-snug">
                                    <a href="{{ route('events.show', $event->slug) }}">
                                        {{ $event->title }}
                                    </a>
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 line-clamp-2">
                                    {{ $event->description }}
                                </p>
                            </div>

                            <div class="space-y-2.5 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                                {{-- Date & Time --}}
                                <div class="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                                    <span class="text-base">🗓️</span>
                                    <span>
                                        {{ $event->starts_at->format('D, d M Y · h:i A') }}
                                    </span>
                                </div>

                                {{-- Venue & Address --}}
                                <div class="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                                    <span class="text-base">🏢</span>
                                    <span class="truncate">
                                        {{ $event->venue ? $event->venue->name : $event->address }}
                                    </span>
                                </div>

                                {{-- Safety Rating Badge --}}
                                <div class="flex items-center justify-between pt-1">
                                    @if ($safetyScore !== null)
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full {{ $safetyScore >= 70 ? 'bg-emerald-500' : ($safetyScore >= 50 ? 'bg-amber-500' : 'bg-red-500') }}"></span>
                                            <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">
                                                {{ __('Venue Safety') }}:
                                                <strong class="{{ $safetyScore >= 70 ? 'text-emerald-600 dark:text-emerald-400' : ($safetyScore >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                                                    {{ round($safetyScore) }}/100
                                                </strong>
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-[11px] text-slate-400 dark:text-slate-500 flex items-center gap-1">
                                            🛡️ {{ __('Safety verified at door') }}
                                        </span>
                                    @endif

                                    {{-- Price Indicator --}}
                                    <div class="text-right">
                                        @if ($lowest <= 0)
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400 text-sm">{{ __('Free RSVP') }}</span>
                                        @else
                                            <span class="text-slate-400 text-[11px]">{{ __('From') }}</span>
                                            <span class="font-bold text-slate-900 dark:text-slate-100 text-sm">৳{{ number_format($lowest) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Card CTA --}}
                            <div class="pt-2">
                                <a href="{{ route('events.show', $event->slug) }}"
                                   class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-indigo-600 dark:hover:bg-indigo-500 text-white font-semibold text-xs flex items-center justify-center gap-1.5 shadow-sm transition">
                                    <span>🎟️ {{ __('Book Passes & View Safety') }}</span>
                                </a>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>

            <div class="pt-4">
                {{ $events->links() }}
            </div>
        @else
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center space-y-3">
                <span class="text-4xl block">🎸</span>
                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ __('No Upcoming Night Events Found') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                    {{ __('There are no published events matching your selected filters right now. Try clearing your search or check back soon!') }}
                </p>
                <div class="pt-2">
                    <a href="{{ route('events.index') }}" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-sm transition inline-block">
                        {{ __('View All Events') }}
                    </a>
                </div>
            </div>
        @endif

    </div>
</x-app-layout>
