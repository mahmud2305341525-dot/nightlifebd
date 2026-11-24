<x-app-layout>
    <div class="max-w-2xl mx-auto p-4">
        <h1 class="text-2xl font-bold mb-2">{{ __('Takedown / Defamation Complaint') }}</h1>
        <p class="text-sm text-slate-600 mb-4">{{ __('Submit a factual complaint about a report or venue record. Moderators will review evidence and preserve the original record where required.') }}</p>
        <form method="POST" action="{{ route('legal.takedown.store') }}" class="space-y-3">
            @csrf
            <input type="email" name="requester_email" required maxlength="190" placeholder="{{ __('Contact email') }}" class="w-full rounded">
            <input type="number" name="report_id" min="1" placeholder="{{ __('Report ID (optional)') }}" class="w-full rounded">
            <input type="number" name="venue_id" min="1" placeholder="{{ __('Venue ID (optional)') }}" class="w-full rounded">
            <textarea name="reason" required minlength="20" maxlength="2000" rows="6" placeholder="{{ __('Explain the complaint and provide evidence links') }}" class="w-full rounded"></textarea>
            @if (config('services.turnstile.site_key'))
                @push('head')<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endpush
                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
            @endif
            <button class="px-4 py-2 rounded bg-indigo-600 text-white">{{ __('Submit complaint') }}</button>
        </form>
    </div>
</x-app-layout>
