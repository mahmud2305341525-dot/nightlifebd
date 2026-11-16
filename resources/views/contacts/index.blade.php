<x-app-layout>
    <div class="max-w-xl mx-auto p-4 space-y-5">
        <h1 class="text-xl font-bold">{{ __('Emergency contacts') }} ({{ $contacts->count() }}/5)</h1>
        @if ($contacts->isEmpty())
            <p class="text-red-600 text-sm">{{ __('Please configure at least one emergency contact.') }}</p>
        @endif

        <ul class="bg-white rounded-xl shadow-sm divide-y">
            @foreach ($contacts as $c)
                <li class="p-3 flex justify-between items-center gap-2 text-sm">
                    <span>
                        <b>{{ $c->name }}</b> - {{ $c->phone }} {{ $c->relation ? '('.$c->relation.')' : '' }}
                        <span class="block text-xs text-slate-500">
                            {{ $c->email ? '✉ '.$c->email : '' }} {{ $c->telegram_chat_id ? '· Telegram '.$c->telegram_chat_id : '' }}
                        </span>
                    </span>
                    <form method="POST" action="{{ route('contacts.destroy', $c) }}">@csrf @method('DELETE')
                        <button class="text-red-600 text-sm">{{ __('Remove') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>

        <form method="POST" action="{{ route('contacts.store') }}" class="bg-white rounded-xl shadow-sm p-4 space-y-3">
            @csrf
            <x-text-input name="name" placeholder="{{ __('Name') }}" class="w-full" :value="old('name')" required />
            <x-text-input name="phone" placeholder="01XXXXXXXXX" class="w-full" :value="old('phone')" required />
            <x-text-input name="relation" placeholder="{{ __('Relation (optional)') }}" class="w-full" :value="old('relation')" />
            <x-text-input name="email" type="email" placeholder="{{ __('Email for automatic alerts (optional)') }}" class="w-full" :value="old('email')" />
            <x-text-input name="telegram_chat_id" placeholder="{{ __('Telegram chat ID for automatic alerts (optional)') }}" class="w-full" :value="old('telegram_chat_id')" />
            <x-input-error :messages="$errors->all()" />
            <x-primary-button>{{ __('Add contact') }}</x-primary-button>
            <p class="text-xs text-slate-500">{{ __('Email and Telegram alerts are sent automatically. SMS opens as a pre-filled message on your phone because browsers cannot send SMS silently.') }}</p>
        </form>
    </div>
</x-app-layout>
