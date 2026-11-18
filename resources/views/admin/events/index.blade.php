<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <span>⚙️</span>
                    <span>{{ __('Event Moderation & Check-In Desk') }}</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Manage published concerts, monitor attendance, and validate attendee entry passes.') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.events.scanner') }}" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm transition flex items-center gap-1.5">
                    <span>📷</span>
                    <span>{{ __('Entry Scanner (QR)') }}</span>
                </a>
                <a href="{{ route('admin.events.create') }}" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-sm transition flex items-center gap-1.5">
                    <span>+</span>
                    <span>{{ __('Publish New Event') }}</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3.5 px-4">{{ __('Event & Category') }}</th>
                            <th class="py-3.5 px-4">{{ __('Venue / Area') }}</th>
                            <th class="py-3.5 px-4">{{ __('Event Timing') }}</th>
                            <th class="py-3.5 px-4">{{ __('Bookings / Capacity') }}</th>
                            <th class="py-3.5 px-4">{{ __('Status') }}</th>
                            <th class="py-3.5 px-4 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($events as $event)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 dark:text-slate-100 text-sm">
                                        {{ $event->title }}
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ $event->categoryLabel() }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">
                                        {{ $event->venue ? $event->venue->name : $event->address }}
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        📍 {{ $event->area }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div>{{ $event->starts_at->format('d M Y, h:i A') }}</div>
                                    <div class="text-[11px] text-slate-400">to {{ $event->ends_at->format('h:i A') }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ $event->bookings_count }} {{ __('bookings') }}
                                    </div>
                                    <div class="text-[11px] text-slate-400">
                                        {{ $event->ticketTiers->sum('booked_count') }} / {{ $event->ticketTiers->sum('capacity') }} {{ __('tickets') }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-full font-bold text-[10px] {{ $event->status === 'published' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300' }}">
                                        {{ ucfirst($event->status) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right space-x-2">
                                    <a href="{{ route('events.show', $event->slug) }}" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                        {{ __('View') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-8 text-slate-400">
                                    {{ __('No events found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $events->links() }}
            </div>
        </div>

    </div>
</x-app-layout>
