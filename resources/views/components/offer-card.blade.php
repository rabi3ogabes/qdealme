@props(['offer'])
@php
    $page = $offer->page;
    $flyer = $offer->flyer;
    $retailer = $offer->retailer;
    $bbox = $offer->bbox ?? ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1];
    $pw = $page->width ?? 1080;
    $ph = $page->height ?? 1440;
    $w = max($bbox['w'] ?? 1, 0.05);
    $h = max($bbox['h'] ?? 1, 0.05);
    $ratio = ($w * $pw).' / '.($h * $ph);
    $imgStyle = sprintf('width:%.4f%%;height:%.4f%%;left:%.4f%%;top:%.4f%%', 100 / $w, 100 / $h, -($bbox['x'] ?? 0) * 100 / $w, -($bbox['y'] ?? 0) * 100 / $h);
@endphp
<article {{ $attributes->merge(['class' => 'card card-hover group relative flex flex-col overflow-hidden']) }}>
    <a href="{{ route('deals.show', ['offer' => $offer->routeSegment()]) }}" class="relative block overflow-hidden bg-surface-2" style="aspect-ratio: 1 / 1" aria-label="{{ $offer->title }}">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 m-auto" style="aspect-ratio: {{ $ratio }}; max-width:100%; max-height:100%; width:auto; height:auto; position:absolute; inset:0;">
                <div class="relative h-full w-full overflow-hidden">
                    <img src="{{ $page->url(1024, 'jpg') }}" alt="{{ $offer->title }}" loading="lazy" decoding="async" class="absolute max-w-none" style="{{ $imgStyle }}; position:absolute;">
                </div>
            </div>
        </div>
        <div class="pointer-events-none absolute inset-x-0 top-0 flex items-start justify-between p-2.5">
            @if ($offer->isVerified())
                <x-badge tone="success" icon="check-badge" size="xs">{{ __('ui.verified_price') }}</x-badge>
            @endif
            @if ($offer->savingPercent())
                <x-badge tone="accent" size="xs">-{{ fmt_number($offer->savingPercent()) }}%</x-badge>
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
