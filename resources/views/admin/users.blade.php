<x-app-layout>
    <div class="max-w-4xl mx-auto p-4">
        <h1 class="text-xl font-bold mb-3 text-slate-800 dark:text-slate-100">{{ __('Users') }}</h1>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm divide-y divide-slate-100 dark:divide-slate-800">
            @foreach ($users as $u)
                <form method="POST" action="{{ route('admin.users.update', $u) }}" class="p-3 flex flex-wrap items-center gap-3 text-sm">
                    @csrf @method('PATCH')
                    <div class="flex-1 min-w-[10rem]"><b class="text-slate-900 dark:text-slate-100">{{ $u->name }}</b><div class="text-xs text-slate-500 dark:text-slate-400">{{ $u->email }} · {{ __('trust') }} {{ number_format($u->trust_score, 2) }}</div></div>
                    <select name="role" class="border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-md text-sm">
                        @foreach ($roles as $r)<option value="{{ $r }}" @selected($u->hasRole($r))>{{ $r }}</option>@endforeach
                    </select>
                    <label class="text-xs text-slate-700 dark:text-slate-300"><input type="checkbox" name="is_banned" value="1" class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500" @checked($u->is_banned)> {{ __('Banned') }}</label>
                    <button class="bg-slate-900 dark:bg-indigo-600 hover:bg-slate-800 dark:hover:bg-indigo-500 text-white rounded px-3 py-1 text-xs font-semibold transition">{{ __('Save') }}</button>
                </form>
            @endforeach
        </div>
        <div class="mt-3">{{ $users->links() }}</div>
    </div>
</x-app-layout>
