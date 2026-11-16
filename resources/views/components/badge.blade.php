@props(['badge' => 'unrated'])
@php
    $color = ['safe' => 'bg-green-600', 'moderate' => 'bg-amber-500', 'caution' => 'bg-red-600'][$badge] ?? 'bg-slate-500';
@endphp
<span {{ $attributes->merge(['class' => "inline-block text-xs font-semibold text-white px-2 py-0.5 rounded-full {$color}"]) }}>{{ __(ucfirst($badge)) }}</span>
