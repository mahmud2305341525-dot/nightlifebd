<x-app-layout>
    <div class="max-w-xl mx-auto p-4 space-y-4 text-center">
        <h1 class="text-2xl font-bold text-red-600">SOS</h1>
        <p class="text-sm text-slate-600">{{ __('Hold the red SOS button for 3 seconds, or double-tap it. It works from every page.') }}</p>
        <button type="button" onclick="window.NLSOS_TRIGGER && window.NLSOS_TRIGGER()" class="w-44 h-44 rounded-full bg-red-600 text-white text-4xl font-extrabold shadow-xl ring-8 ring-red-200">SOS</button>
        <a href="tel:999" class="block rounded-lg bg-slate-900 text-white py-3 font-bold">📞 {{ __('Call 999') }}</a>
        <div class="text-left text-sm bg-white rounded-xl shadow-sm p-4 space-y-1">
            <p>✔ {{ __('Opens a pre-filled SMS with your Google Maps location for each emergency contact.') }}</p>
            <p>✔ {{ __('Emails / Telegram-messages your contacts automatically (if configured).') }}</p>
            <p>✔ {{ __('Works offline: SMS and call buttons and nearest help points are cached on this phone.') }}</p>
            <p>⏱ {{ __('A repeat press within 60 seconds asks for confirmation.') }}</p>
        </div>
    </div>
</x-app-layout>
