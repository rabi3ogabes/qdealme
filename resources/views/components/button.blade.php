@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button', 'icon' => null])
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-[background-color,color,transform,box-shadow] duration-200 ease-(--ease-out-soft) focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60 active:scale-[0.98]';
    $variants = [
        'primary' => 'bg-primary text-primary-fg shadow-(--shadow-soft) hover:bg-primary-hover',
        'accent' => 'bg-accent text-accent-fg shadow-(--shadow-soft) hover:brightness-105',
        'secondary' => 'bg-surface-2 text-text hover:bg-surface-3',
        'outline' => 'border border-border-strong bg-surface text-text hover:border-primary hover:text-primary',
        'ghost' => 'text-text-2 hover:bg-surface-2 hover:text-text',
        'danger' => 'bg-danger-soft text-danger hover:brightness-95',
        'white' => 'bg-white text-ink-900 shadow-(--shadow-soft) hover:bg-sand-100',
    ];
    $sizes = ['sm' => 'h-9 px-3.5 text-sm', 'md' => 'h-11 px-5 text-sm', 'lg' => 'h-12 px-6 text-base', 'icon' => 'size-11'];
    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
