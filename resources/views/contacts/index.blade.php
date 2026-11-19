<x-app-layout>
    <div class="max-w-xl mx-auto p-4 space-y-5">
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ __('Emergency contacts') }} ({{ $contacts->count() }}/5)</h1>
        @if ($contacts->isEmpty())
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm p-6 text-center border border-slate-200 dark:border-slate-800 space-y-2">
                <div class="text-3xl">👥</div>
                <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">{{ __('No emergency contacts added yet') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                    {{ __('Your safety circle receives automated alerts if your safety timer expires, an active trip goes silent, or you trigger SOS.') }}
                </p>
            </div>
        @else
            <ul class="bg-white dark:bg-slate-900 rounded-xl shadow-sm divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200 dark:border-slate-800">
                @foreach ($contacts as $c)
                    <li class="p-3 flex justify-between items-center gap-2 text-sm">
                        <span>
                            <b class="text-slate-900 dark:text-slate-100">{{ $c->name }}</b> <span class="text-slate-600 dark:text-slate-400">- {{ $c->phone }} {{ $c->relation ? '('.$c->relation.')' : '' }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                {{ $c->email ? '✉ '.$c->email : '' }} {{ $c->telegram_chat_id ? '· Telegram '.$c->telegram_chat_id : '' }}
                            </span>
                        </span>
                        <form method="POST" action="{{ route('contacts.destroy', $c) }}">@csrf @method('DELETE')
                            <button class="text-red-600 hover:text-red-700 text-sm font-medium">{{ __('Remove') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('contacts.store') }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-4 space-y-3">
            @csrf
            <x-text-input name="name" placeholder="{{ __('Name') }}" class="w-full" :value="old('name')" required />
            <x-text-input name="phone" placeholder="01XXXXXXXXX" class="w-full" :value="old('phone')" required />
            <x-text-input name="relation" placeholder="{{ __('Relation (optional)') }}" class="w-full" :value="old('relation')" />
            <x-text-input name="email" type="email" placeholder="{{ __('Email for automatic alerts (optional)') }}" class="w-full" :value="old('email')" />
            <x-text-input name="telegram_chat_id" placeholder="{{ __('Telegram chat ID for automatic alerts (optional)') }}" class="w-full" :value="old('telegram_chat_id')" />
            <x-input-error :messages="$errors->all()" />
            <x-primary-button>{{ __('Add contact') }}</x-primary-button>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Email and Telegram alerts are sent automatically. SMS opens as a pre-filled message on your phone because browsers cannot send SMS silently.') }}</p>
        </form>
    </div>
</x-app-layout>
