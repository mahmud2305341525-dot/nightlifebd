<x-app-layout>
    @include('partials.leaflet')
    <div class="max-w-2xl mx-auto p-4">
        <h1 class="text-xl font-bold mb-3">{{ __('Edit venue') }}</h1>
        <form method="POST" action="{{ route('partner.venues.update', $venue) }}" class="bg-white rounded-xl shadow-sm p-5">
            @csrf @method('PUT')
            @include('partner.venues._form')
            <button class="mt-4 w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-md py-2.5 font-semibold">{{ __('Save changes') }}</button>
        </form>
    </div>
</x-app-layout>
