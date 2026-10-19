@props(['offer'])
@php
    $flyer = $offer->flyer;
    $retailer = $offer->retailer;
@endphp
<article {{ $attributes->merge(['class' => 'card card-hover group relative flex flex-col overflow-hidden']) }}>
    <a href="{{ route('deals.show', ['offer' => $offer->routeSegment()]) }}" class="relative block overflow-hidden bg-surface-2" style="aspect-ratio: 4 / 3" aria-label="{{ $offer->title }}">
        <x-offer-crop :offer="$offer" class="absolute inset-0 p-3" />
        <div class="pointer-events-none absolute inset-x-0 top-0 flex items-start justify-between p-2.5">
            @if ($offer->isVerified())
                <x-badge tone="success" icon="check-badge" size="xs">{{ __('ui.verified_price') }}</x-badge>
            @endif
            @if ($offer->savingPercent())
                <x-badge tone="accent" size="xs" dir="ltr">-{{ fmt_number($offer->savingPercent()) }}%</x-badge>
            @endif
        </div>
    </a>
    <div class="flex flex-1 flex-col gap-2 p-3.5">
        <div class="flex items-center gap-2 text-xs text-muted">
            <x-retailer-logo :retailer="$retailer" class="size-6" />
            <span class="truncate">{{ $retailer->name }}</span>
        </div>
        <h3 class="line-clamp-2 text-[0.95rem] font-semibold leading-snug">
            <a href="{{ route('deals.show', ['offer' => $offer->routeSegment()]) }}" class="after:absolute after:inset-0 after:content-['']">{{ $offer->title }}</a>
        </h3>
        <x-price-block :offer="$offer" class="mt-auto" />
        <p class="text-xs text-muted">{{ __('ui.valid_until', ['date' => fmt_date($flyer->valid_until)]) }}</p>
    </div>
</article>
