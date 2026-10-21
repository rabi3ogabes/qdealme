@props(['retailer', 'size' => null])
<span {{ $attributes->merge(['class' => 'grid shrink-0 place-items-center overflow-hidden rounded-full border border-border bg-white']) }}>
    @if ($retailer->logoUrl())
        <img src="{{ $retailer->logoUrl() }}" alt="" width="80" height="80" loading="lazy" decoding="async" class="h-full w-full object-cover">
    @else
        <span class="text-sm font-bold text-ink-800">{{ mb_substr($retailer->name_en, 0, 1) }}</span>
    @endif
</span>
