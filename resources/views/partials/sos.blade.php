<button id="sos-btn" type="button" style="touch-action:manipulation;-webkit-user-select:none;user-select:none"
        class="fixed bottom-5 right-5 z-50 w-16 h-16 rounded-full bg-red-600 text-white font-extrabold shadow-xl ring-4 ring-red-200 active:scale-95 select-none"
        title="Hold 3 seconds or double-tap">SOS</button>
@include('partials.sos-modal')
<script>
    window.NLSOS = {
        user: @json(auth()->user()->name),
        contacts: @json(auth()->user()->emergencyContacts()->get(['name', 'phone'])),
        endpoint: @json(route('sos.trigger')),
        csrf: @json(csrf_token()),
    };
</script>
<script src="/js/sos.js" defer></script>
