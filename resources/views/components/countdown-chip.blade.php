@props(['countdown', 'size' => 'sm'])
@php
    $tone = match ($countdown->tone()) {
        'danger' => 'danger',
        'warning' => 'warning',
        'info' => 'info',
        'muted' => 'muted',
        default => 'neutral',
    };
@endphp
<x-badge :tone="$tone" icon="clock" :size="$size" {{ $attributes }}>
    <time>{{ $countdown->label() }}</time>
</x-badge>
