@if (config('nightlife.demo_enabled', false))
<div class="fixed bottom-4 left-4 z-[9999] font-sans">
    <a href="{{ route('demo.index') }}"
        class="bg-slate-900/95 hover:bg-slate-900 text-amber-400 font-bold border border-amber-500/50 shadow-2xl rounded-full px-4 py-2 text-xs flex items-center gap-2 transition-all transform hover:scale-105 active:scale-95 backdrop-blur-md cursor-pointer select-none">
        <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
        </span>
        <span>⚡ Demo Simulator</span>
        <svg class="w-3.5 h-3.5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
        </svg>
    </a>
</div>
@endif
