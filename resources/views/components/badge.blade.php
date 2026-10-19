@props(['tone' => 'neutral', 'icon' => null, 'size' => 'sm'])
@php
    $tones = [
        'neutral' => 'bg-surface-2 text-text-2',
        'primary' => 'bg-primary text-primary-fg',
        'primary-soft' => 'bg-primary-soft text-primary',
        'accent' => 'bg-accent text-accent-fg',
        'success' => 'bg-success-soft text-success',
        'warning' => 'bg-warning-soft text-warning',
        'danger' => 'bg-danger-soft text-danger',
        'info' => 'bg-info-soft text-info',
        'muted' => 'bg-surface-3 text-muted',
        'glass' => 'bg-black/55 text-white backdrop-blur-md',
    ];
    $sizes = ['xs' => 'px-2 py-0.5 text-[11px]', 'sm' => 'px-2.5 py-1 text-xs', 'md' => 'px-3 py-1.5 text-sm'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full font-semibold whitespace-nowrap '.($tones[$tone] ?? $tones['neutral']).' '.($sizes[$size] ?? $sizes['sm'])]) }}>
    @if ($icon)<x-svg-icon :name="$icon" class="size-3.5" />@endif
    {{ $slot }}
</span>
