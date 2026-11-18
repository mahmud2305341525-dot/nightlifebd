<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl leading-tight text-slate-800 dark:text-slate-100">{{ __('Edit Pending Report') }}</h2>
            <a href="{{ route('reports.mine') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← {{ __('Back to My Reports') }}</a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto p-4">
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm p-6 border border-slate-200 dark:border-slate-800 space-y-4">
            <div class="flex items-center gap-2 p-3 bg-amber-50 dark:bg-amber-950/40 rounded-lg text-xs text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                <span>ℹ️</span>
                <span>{{ __('Editing a pending report will automatically trigger a new AI classification and update the moderation queue.') }}</span>
            </div>

            <form method="POST" action="{{ route('reports.update', $report) }}" class="space-y-4">
                @csrf
                @method('PUT')

                @if ($report->venue)
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Venue') }}</label>
                        <input type="text" disabled value="{{ $report->venue->name }} ({{ $report->venue->area }})" class="mt-1 w-full text-sm bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-md text-slate-500 dark:text-slate-400">
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Description of what happened') }}</label>
                    <textarea name="body" rows="4" required minlength="10" maxlength="500"
                        class="mt-1 w-full text-sm rounded-md border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">{{ old('body', $report->body) }}</textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-1" />
                </div>

                @if ($report->kind === 'review')
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Safety & Service Rating (1-5 stars)') }}</label>
                        <select name="rating" class="mt-1 w-full text-sm rounded-md border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                            @for ($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}" @selected(old('rating', $report->rating) == $i)>{{ $i }} {{ str_repeat('★', $i) }}</option>
                            @endfor
                        </select>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Incident Date & Time') }}</label>
                    <input type="datetime-local" name="incident_time"
                        value="{{ old('incident_time', $report->incident_time?->format('Y-m-d\TH:i')) }}"
                        class="mt-1 w-full text-sm rounded-md border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
                    <x-input-error :messages="$errors->get('incident_time')" class="mt-1" />
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1" @checked(old('is_anonymous', $report->is_anonymous)) class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500">
                    <label for="is_anonymous" class="text-sm text-slate-700 dark:text-slate-300">{{ __('Submit as anonymous community report') }}</label>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <a href="{{ route('reports.mine') }}" class="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-md text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">{{ __('Cancel') }}</a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-md shadow-sm transition">
                        {{ __('Save Changes') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
