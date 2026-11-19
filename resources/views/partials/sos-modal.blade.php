<div id="sos-modal" class="hidden fixed inset-0 z-[60] bg-black/70 backdrop-blur-sm flex items-end sm:items-center justify-center p-4" style="font-family:system-ui,sans-serif">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 rounded-2xl w-full max-w-md p-5 space-y-3 max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold text-red-600 dark:text-red-400 flex items-center gap-1.5">
                <span>🆘</span> <span>SOS / জরুরি সাহায্য</span>
            </h2>
            <button id="sos-close" type="button" class="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-2xl leading-none" aria-label="Close">&times;</button>
        </div>
        <p id="sos-status" class="text-sm text-slate-700 dark:text-slate-300"></p>
        <div id="sos-actions" class="space-y-2"></div>
        <div id="sos-havens" class="text-sm"></div>
        <p class="text-xs text-slate-500 dark:text-slate-400">Tap WhatsApp or SMS to send your live location. Your contacts also receive automated email/Telegram alerts.</p>
    </div>
</div>
