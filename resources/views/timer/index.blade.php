<x-app-layout>
    <div class="max-w-xl mx-auto p-4 space-y-4">
        <h1 class="text-xl font-bold">⏱️ {{ __('Safety timer (dead-man switch)') }}</h1>
        <p class="text-sm text-slate-600">{{ __('Set when you expect to arrive. If you do not tap "I am safe" by then, your emergency contacts are alerted automatically.') }}</p>
        @unless ($hasContacts)
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm">{{ __('Add an emergency contact first.') }} <a class="underline" href="{{ route('contacts.index') }}">{{ __('Add contacts') }}</a></div>
        @endunless

        <form method="POST" action="{{ route('timer.store') }}" class="bg-white rounded-xl shadow-sm p-4 space-y-3 text-sm">
            @csrf
            <div class="flex gap-2">
                @foreach ([15, 30, 60, 90] as $m)<button type="button" data-m="{{ $m }}" class="quick border rounded-md px-3 py-1">+{{ $m }} {{ __('min') }}</button>@endforeach
            </div>
            <label class="block">{{ __('I will arrive by') }}
                <input type="datetime-local" id="due_at" name="due_at" value="{{ old('due_at') }}" class="mt-1 w-full border-slate-300 rounded-md" required>
            </label>
            <input name="note" maxlength="120" placeholder="{{ __('Note (e.g. going home from Gulshan by CNG)') }}" value="{{ old('note') }}" class="w-full border-slate-300 rounded-md">
            <x-input-error :messages="$errors->all()" />
            <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-md py-2.5 font-semibold">{{ __('Start timer') }}</button>
        </form>

        <div class="space-y-2">
            @foreach ($timers as $t)
                @php $st = $t->status(); @endphp
                <div class="bg-white rounded-xl shadow-sm p-3 text-sm flex justify-between items-center gap-2">
                    <div>
                        <div class="font-semibold">{{ $t->due_at->format('d M, h:i A') }} <span class="text-xs px-2 py-0.5 rounded-full {{ ['running' => 'bg-blue-100 text-blue-800', 'overdue' => 'bg-amber-100 text-amber-800', 'alerted' => 'bg-red-100 text-red-800', 'confirmed' => 'bg-green-100 text-green-800'][$st] }}">{{ __($st) }}</span></div>
                        <div class="text-xs text-slate-500">{{ $t->note }}</div>
                        @if ($st === 'running')<div class="text-xs text-slate-500 countdown" data-due="{{ $t->due_at->toIso8601String() }}"></div>@endif
                    </div>
                    @if (in_array($st, ['running', 'overdue']))
                        <form method="POST" action="{{ route('timer.confirm', $t) }}">@csrf <button class="bg-green-600 text-white rounded-md px-3 py-1.5">✔ {{ __('I am safe') }}</button></form>
                    @else
                        <form method="POST" action="{{ route('timer.destroy', $t) }}">@csrf @method('DELETE') <button class="text-xs text-slate-400 underline">{{ __('Remove') }}</button></form>
                    @endif
                </div>
            @endforeach
        </div>
        <a href="{{ route('fakecall') }}" class="block text-center border border-slate-300 rounded-md py-2 text-sm">📞 {{ __('Need an excuse to leave? Fake call') }}</a>
        <p class="text-xs text-slate-500">{{ __('Alerts are checked every minute by the scheduler (php artisan schedule:work).') }}</p>
    </div>
    @push('scripts')
        <script>
            const pad = n => String(n).padStart(2, '0');
            document.querySelectorAll('.quick').forEach(b => b.onclick = () => {
                const d = new Date(Date.now() + parseInt(b.dataset.m, 10) * 60000);
                document.getElementById('due_at').value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            });
            setInterval(() => document.querySelectorAll('.countdown').forEach(el => {
                const s = Math.max(0, Math.floor((new Date(el.dataset.due) - Date.now()) / 1000));
                el.textContent = '⏳ ' + pad(Math.floor(s / 3600)) + ':' + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60);
            }), 1000);
        </script>
    @endpush
</x-app-layout>
