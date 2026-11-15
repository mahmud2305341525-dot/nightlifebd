<x-app-layout>
    <div class="max-w-4xl mx-auto p-4">
        <h1 class="text-xl font-bold mb-3">{{ __('Users') }}</h1>
        <div class="bg-white rounded-xl shadow-sm divide-y">
            @foreach ($users as $u)
                <form method="POST" action="{{ route('admin.users.update', $u) }}" class="p-3 flex flex-wrap items-center gap-3 text-sm">
                    @csrf @method('PATCH')
                    <div class="flex-1 min-w-[10rem]"><b>{{ $u->name }}</b><div class="text-xs text-slate-500">{{ $u->email }} · {{ __('trust') }} {{ number_format($u->trust_score, 2) }}</div></div>
                    <select name="role" class="border-slate-300 rounded-md text-sm">
                        @foreach ($roles as $r)<option value="{{ $r }}" @selected($u->hasRole($r))>{{ $r }}</option>@endforeach
                    </select>
                    <label class="text-xs"><input type="checkbox" name="is_banned" value="1" class="rounded" @checked($u->is_banned)> {{ __('Banned') }}</label>
                    <button class="bg-slate-900 text-white rounded px-3 py-1 text-xs">{{ __('Save') }}</button>
                </form>
            @endforeach
        </div>
        <div class="mt-3">{{ $users->links() }}</div>
    </div>
</x-app-layout>
