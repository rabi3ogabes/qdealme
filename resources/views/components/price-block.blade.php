@props(['offer', 'size' => 'md'])
@php
    $saving = $offer->saving();
    $percent = $offer->savingPercent();
    $big = $size === 'lg' ? 'text-3xl' : 'text-xl';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-x-3 gap-y-1']) }}>
    <span class="price {{ $big }} font-bold leading-none text-primary" dir="ltr">{{ fmt_price($offer->price) }}</span>
    @if ($offer->old_price && $saving)
        <span class="price text-sm text-muted line-through" dir="ltr">{{ fmt_price($offer->old_price) }}</span>
        @if ($percent)
            <x-badge tone="accent" size="xs">-{{ fmt_number($percent) }}%</x-badge>
        @endif
    @endif
    @if ($offer->unit_price && $offer->unit && $size === 'lg')
        <span class="basis-full text-xs text-muted">{{ __('ui.per_unit', ['price' => fmt_price($offer->unit_price, true, 2), 'unit' => __('offer.unit.'.$offer->unit)]) }}</span>
    @endif
</div>
