<x-app-layout>
    <div class="max-w-3xl mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">{{ __('Submit an event') }}</h1>
        <form method="POST" action="{{ route('partner.events.store') }}" class="space-y-3">
            @csrf
            <input name="title" required maxlength="150" placeholder="{{ __('Event title') }}" class="w-full rounded">
            <select name="category" required class="w-full rounded">@foreach ($categories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            <select name="venue_id" required class="w-full rounded"><option value="">Select venue</option>@foreach ($venues as $venue)<option value="{{ $venue->id }}">{{ $venue->name }} · {{ $venue->area }}</option>@endforeach</select>
            <input name="address" required maxlength="255" placeholder="{{ __('Venue address') }}" class="w-full rounded">
            <div class="grid sm:grid-cols-2 gap-2">
                <input type="datetime-local" name="starts_at" required class="rounded">
                <input type="datetime-local" name="ends_at" required class="rounded">
            </div>
            <textarea name="description" required maxlength="5000" rows="5" placeholder="{{ __('Event description') }}" class="w-full rounded"></textarea>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded">{{ __('Submit for approval') }}</button>
        </form>
    </div>
</x-app-layout>
