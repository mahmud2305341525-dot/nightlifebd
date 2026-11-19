@props(['badge' => 'unrated'])
@php
    $key = strtolower((string) $badge);
    $configs = [
        'safe' => [
            'icon' => '🛡️',
            'label' => __('Safe'),
            'style' => 'bg-emerald-600 dark:bg-emerald-700 text-white border border-emerald-700/30',
        ],
        'moderate' => [
            'icon' => '⚠️',
            'label' => __('Moderate'),
            'style' => 'bg-amber-600 dark:bg-amber-700 text-white border border-amber-700/30',
        ],
        'caution' => [
            'icon' => '⛔',
            'label' => __('Caution'),
            'style' => 'bg-rose-600 dark:bg-rose-700 text-white border border-rose-700/30',
        ],
        'unrated' => [
            'icon' => '❔',
            'label' => __('Unrated'),
            'style' => 'bg-slate-600 dark:bg-slate-700 text-white border border-slate-700/30',
        ],
    ];
    $c = $configs[$key] ?? $configs['unrated'];
@endphp
<span role="status" aria-label="{{ __('Safety rating: :badge', ['badge' => $c['label']]) }}"
    {{ $attributes->merge(['class' => "inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full shadow-xs transition-colors {$c['style']}"]) }}>
    <span aria-hidden="true" class="text-[10px] leading-none">{{ $c['icon'] }}</span>
    <span>{{ $c['label'] }}</span>
</span>
