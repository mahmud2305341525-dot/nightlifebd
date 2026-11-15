<div id="sos-modal" class="hidden fixed inset-0 z-[60] bg-black/70 flex items-end sm:items-center justify-center p-4" style="font-family:system-ui,sans-serif">
    <div class="bg-white rounded-2xl w-full max-w-md p-5 space-y-3 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold text-red-600">SOS / জরুরি সাহায্য</h2>
            <button id="sos-close" type="button" class="text-slate-500 text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <p id="sos-status" class="text-sm text-slate-700"></p>
        <div id="sos-actions" class="space-y-2"></div>
        <div id="sos-havens" class="text-sm"></div>
        <p class="text-xs text-slate-500">Browsers cannot send SMS silently: tap each button to send. Your contacts also get email/Telegram automatically when they are set up.</p>
    </div>
</div>
