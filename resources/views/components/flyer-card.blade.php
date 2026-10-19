@props(['flyer', 'today' => null, 'eager' => false])
@php
    $cover = $flyer->coverPage();
    $countdown = $flyer->countdown($today ?? local_today(current_city()));
    $retailer = $flyer->retailer;
@endphp
<article {{ $attributes->merge(['class' => 'card card-hover group relative flex flex-col overflow-hidden']) }}>
    <a href="{{ $flyer->url() }}" class="relative block overflow-hidden bg-surface-2" style="aspect-ratio: 3 / 4" aria-label="{{ $retailer->name }} – {{ $flyer->title }}">
        @if ($cover)
            <img src="{{ $cover->url(640) }}"
                 @if ($cover->srcset()) srcset="{{ $cover->srcset() }}" sizes="(min-width: 1280px) 22vw, (min-width: 768px) 30vw, 46vw" @endif
                 width="{{ $cover->width ?? 1080 }}" height="{{ $cover->height ?? 1440 }}"
                 alt="{{ $retailer->name }} – {{ $flyer->title }}"
                 loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async"
                 class="h-full w-full object-cover transition-transform duration-500 ease-(--ease-out-soft) group-hover:scale-[1.03]">
        @else
            <div class="grid h-full place-items-center text-muted"><x-svg-icon name="photo" class="size-10" /></div>
        @endif

        <div class="pointer-events-none absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-2.5">
            @if ($flyer->isSponsoredNow())
                <x-badge tone="accent" icon="sparkles" size="xs">{{ __('ui.sponsored') }}</x-badge>
            @else
                <span></span>
            @endif
            @if ($flyer->shop_online_url)
                <x-badge tone="glass" icon="shopping-bag" size="xs">{{ __('ui.shop_online') }}</x-badge>
            @elseif ($flyer->whatsapp_order_url)
                <x-badge tone="glass" icon="whatsapp" size="xs">{{ __('ui.whatsapp_order') }}</x-badge>
            @endif
        </div>
    </a>

    <div class="flex flex-1 flex-col gap-3 p-3.5">
        <div class="flex items-center gap-3">
            <a href="{{ route('stores.show', $retailer) }}" class="shrink-0" aria-label="{{ $retailer->name }}">
                <x-retailer-logo :retailer="$retailer" class="size-10" />
            </a>
            <div class="min-w-0">
                <h3 class="truncate text-[0.95rem] font-semibold leading-tight">
                    <a href="{{ $flyer->url() }}" class="after:absolute after:inset-0 after:content-['']">{{ $retailer->name }}</a>
                </h3>
                <p class="truncate text-sm text-muted">{{ $flyer->title }}</p>
            </div>
        </div>
        <div class="mt-auto flex items-center justify-between gap-2 text-xs text-muted">
            <span class="inline-flex items-center gap-1"><x-svg-icon name="document" class="size-3.5" />{{ trans_choice('ui.pages', $flyer->page_count, ['count' => fmt_number($flyer->page_count)]) }}</span>
            <x-countdown-chip :countdown="$countdown" class="relative z-10" />
        </div>
    </div>
</article>
