@php
    $pagesData = $pages->map(fn ($p) => [
        'number' => $p->number,
        'url' => $p->url(1600),
        'srcset' => $p->srcset(),
        'thumb' => $p->url(320),
        'ratio' => $p->aspectRatio(),
        'ar' => $p->width && $p->height ? round($p->width / $p->height, 5) : 0.75,
        'width' => $p->width,
        'height' => $p->height,
    ])->values();

    $pageNumbers = $pages->pluck('number', 'id');
    $offersData = $offersByPage->mapWithKeys(fn ($items, $pageId) => [
        $pageNumbers[$pageId] ?? 0 => $items->map(fn ($o) => [
            'id' => $o->id,
            'title' => $o->title,
            'brand' => $o->brand,
            'price' => fmt_price($o->price),
            'old_price' => $o->old_price && $o->savingAmount() ? fmt_price($o->old_price) : null,
            'percent' => $o->savingPercent() ? fmt_number($o->savingPercent()) : null,
            'unit' => $o->unit_price && $o->unit ? __('ui.per_unit', ['price' => fmt_price($o->unit_price, true, 2), 'unit' => __('offer.unit.'.$o->unit)]) : null,
            'bbox' => $o->bbox,
            'url' => route('deals.show', ['offer' => $o->routeSegment()]),
        ])->values(),
    ]);
    $shareTitle = $retailer->name.' – '.$flyer->title;
    $totalOffers = $offersByPage->flatten()->count();
@endphp
<x-layouts.viewer :seo="$seo">
    <div x-data="flyerViewer({
            pages: @js($pagesData),
            start: {{ $currentPage }},
            rtl: {{ is_rtl() ? 'true' : 'false' }},
            offersByPage: @js($offersData),
            highlightOffer: {{ $highlightOffer }},
            shareTitle: @js($shareTitle),
            shareUrl: @js(__('ui.copy_link')),
         })"
         class="relative flex h-dvh flex-col bg-bg">

        {{-- Top bar --}}
        <header class="absolute inset-x-0 top-0 z-30 transition-opacity duration-300"
                :class="chromeVisible ? 'opacity-100' : 'opacity-0 pointer-events-none'">
            <div class="flex items-center gap-2 bg-gradient-to-b from-bg via-bg/90 to-transparent px-3 pt-[max(env(safe-area-inset-top),0.75rem)] pb-6 sm:px-5">
                <a href="{{ route('stores.show', $retailer) }}" class="grid size-10 shrink-0 place-items-center rounded-full border border-border bg-surface/90 backdrop-blur hover:bg-surface-2" aria-label="{{ __('ui.close_viewer') }}">
                    <x-svg-icon name="arrow-left" class="size-5 rtl:-scale-x-100" />
                </a>
                <a href="{{ route('stores.show', $retailer) }}" class="flex min-w-0 items-center gap-2.5">
                    <x-retailer-logo :retailer="$retailer" class="size-10" />
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">{{ $retailer->name }}</span>
                        <h1 class="truncate text-xs text-muted">{{ $flyer->title }}</h1>
                    </span>
                </a>
                <div class="ms-auto flex items-center gap-1.5">
                    <span class="hidden sm:inline-flex"><x-countdown-chip :countdown="$countdown" /></span>
                    @if ($flyer->shop_online_url)
                        <a href="{{ $flyer->shop_online_url }}" target="_blank" rel="noopener nofollow" class="chip hidden bg-primary text-primary-fg hover:bg-primary-hover md:inline-flex"><x-svg-icon name="shopping-bag" class="size-4" />{{ __('ui.shop_online') }}</a>
                    @endif
                    @if ($flyer->whatsapp_order_url)
                        <a href="{{ $flyer->whatsapp_order_url }}" target="_blank" rel="noopener nofollow" class="chip hidden bg-[#1f9d55] text-white hover:brightness-110 md:inline-flex"><x-svg-icon name="whatsapp" class="size-4" />{{ __('ui.whatsapp_order') }}</a>
                    @endif
                    <div x-data="toggleAction({ url: @js(route('flyers.favorite', $flyer)), active: @js($isFavorite), authenticated: @js(auth()->check()), loginUrl: @js(route('login')) })" data-error-message="{{ __('ui.error_500_title') }}">
                        <button type="button" @click="toggle()" :disabled="busy" class="grid size-10 place-items-center rounded-full border border-border bg-surface/90 backdrop-blur hover:bg-surface-2" :aria-pressed="active" aria-label="{{ __('ui.save') }}">
                            <x-svg-icon name="bookmark" class="size-5" x-bind:class="active ? 'fill-current text-primary' : ''" />
                        </button>
                    </div>
                    <button type="button" @click="share()" class="grid size-10 place-items-center rounded-full border border-border bg-surface/90 backdrop-blur hover:bg-surface-2" aria-label="{{ __('ui.share') }}"><x-svg-icon name="share" class="size-5" /></button>
                    <button type="button" @click="detailsOpen = true" class="grid size-10 place-items-center rounded-full border border-border bg-surface/90 backdrop-blur hover:bg-surface-2" aria-label="{{ __('ui.details') }}"><x-svg-icon name="info" class="size-5" /></button>
                </div>
            </div>
            @if ($flyer->isExpired())
                <div class="mx-3 -mt-3 rounded-2xl border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-warning sm:mx-5" role="status">
                    <strong class="font-semibold">{{ __('ui.expired_banner') }}.</strong>
                    {{ __('ui.expired_banner_text', ['store' => $retailer->name]) }}
                    <a href="{{ route('stores.show', $retailer) }}" class="font-semibold underline">{{ __('ui.current_flyers_from', ['store' => $retailer->name]) }}</a>
                </div>
            @endif
        </header>

        {{-- Stage --}}
        <div id="viewer-stage" x-ref="stage"
             class="viewer-stage relative flex flex-1 items-center justify-center overflow-hidden"
             :class="scale > 1 ? (panning ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-default'"
             @pointerdown="onPointerDown($event)" @pointermove="onPointerMove($event)" @pointerup="onPointerUp($event)" @pointercancel="panning = false; swipeStart = null"
             @wheel="onWheel($event)"
             @touchstart="onTouchStart($event)" @touchmove="onTouchMove($event)" @touchend="onTouchEnd($event)"
             role="region" aria-roledescription="carousel" aria-label="{{ $shareTitle }}">
            <div class="relative flex h-full w-full items-center justify-center px-2 pt-20 pb-28 sm:px-16"
                 :style="`transform: ${transform}; transition: ${panning || pinch ? 'none' : 'transform 220ms cubic-bezier(.22,1,.36,1)'}`">
                <template x-for="p in pages" :key="p.number">
                    <div x-show="p.number === current" :data-page="p.number" class="relative max-h-full max-w-full" :style="`aspect-ratio: ${p.ratio}; width: min(100%, calc((100dvh - 12rem) * ${p.ar}))`">
                        <img :src="p.url" :srcset="p.srcset" sizes="(min-width: 1024px) 60vw, 100vw"
                             :width="p.width" :height="p.height"
                             :alt="@js($shareTitle.' – '.__('ui.on_page', ['page' => ''])) + p.number"
                             class="page-img h-full w-full rounded-lg shadow-(--shadow-lift)" draggable="false"
                             :loading="p.number === current ? 'eager' : 'lazy'">
                        <template x-for="offer in (offersByPage[p.number] || [])" :key="offer.id">
                            <button type="button" @pointerdown.stop @pointerup.stop @click.stop="selectOffer(offer)"
                                    class="absolute rounded-md border-2 border-accent/70 bg-accent/10 transition hover:bg-accent/25 focus-visible:bg-accent/25"
                                    :class="selectedOffer && selectedOffer.id === offer.id ? 'ring-4 ring-accent/50 bg-accent/25' : ''"
                                    :style="hotspotStyle(offer)" :aria-label="offer.title + ' ' + offer.price"></button>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Side arrows (desktop) --}}
            <button type="button" @click="backward()" x-show="hasPrev" x-transition.opacity
                    class="absolute start-3 top-1/2 z-20 hidden size-12 -translate-y-1/2 place-items-center rounded-full border border-border bg-surface/90 shadow-(--shadow-soft) backdrop-blur hover:bg-surface sm:grid"
                    aria-label="{{ __('ui.prev_page') }}">
                <x-svg-icon name="chevron-left" class="size-6 rtl:-scale-x-100" />
            </button>
            <button type="button" @click="forward()" x-show="hasNext" x-transition.opacity
                    class="absolute end-3 top-1/2 z-20 hidden size-12 -translate-y-1/2 place-items-center rounded-full border border-border bg-surface/90 shadow-(--shadow-soft) backdrop-blur hover:bg-surface sm:grid"
                    aria-label="{{ __('ui.next_page') }}">
                <x-svg-icon name="chevron-right" class="size-6 rtl:-scale-x-100" />
            </button>
        </div>

        {{-- Bottom toolbar --}}
        <footer class="absolute inset-x-0 bottom-0 z-30 transition-opacity duration-300" :class="chromeVisible ? 'opacity-100' : 'opacity-0 pointer-events-none'">
            <div class="bg-gradient-to-t from-bg via-bg/90 to-transparent px-3 pt-8 pb-[max(env(safe-area-inset-bottom),0.75rem)]">
                <div class="mx-auto flex max-w-xl items-center justify-between gap-2 rounded-full border border-border bg-surface/95 p-1.5 shadow-(--shadow-lift) backdrop-blur">
                    <button type="button" @click="backward()" :disabled="!hasPrev" class="grid size-10 place-items-center rounded-full hover:bg-surface-2 disabled:opacity-30" aria-label="{{ __('ui.prev_page') }}"><x-svg-icon name="chevron-left" class="size-5 rtl:-scale-x-100" /></button>
                    <button type="button" @click="thumbsOpen = !thumbsOpen" class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm font-semibold hover:bg-surface-2" :aria-expanded="thumbsOpen">
                        <x-svg-icon name="grid" class="size-4" />
                        <span aria-live="polite" class="tabular-nums" dir="ltr"><span x-text="current"></span> / <span x-text="total"></span></span>
                    </button>
                    @if ($totalOffers > 0)
                        <button type="button" @click="offersOpen = true" class="relative inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-sm font-semibold text-primary hover:bg-surface-2">
                            <x-svg-icon name="tag" class="size-4" />
                            <span class="hidden sm:inline">{{ __('ui.offers_on_page') }}</span>
                            <span class="rounded-full bg-accent px-1.5 text-[11px] text-accent-fg" x-text="currentOffers.length"></span>
                        </button>
                    @endif
                    <div class="flex items-center">
                        <button type="button" @click="zoomTo(scale / 1.4)" :disabled="scale <= 1" class="hidden size-10 place-items-center rounded-full hover:bg-surface-2 disabled:opacity-30 sm:grid" aria-label="{{ __('ui.zoom_out') }}"><x-svg-icon name="zoom-out" class="size-5" /></button>
                        <button type="button" @click="zoomTo(scale * 1.4)" :disabled="scale >= 5" class="grid size-10 place-items-center rounded-full hover:bg-surface-2 disabled:opacity-30" aria-label="{{ __('ui.zoom_in') }}"><x-svg-icon name="zoom-in" class="size-5" /></button>
                    </div>
                    <button type="button" @click="forward()" :disabled="!hasNext" class="grid size-10 place-items-center rounded-full hover:bg-surface-2 disabled:opacity-30" aria-label="{{ __('ui.next_page') }}"><x-svg-icon name="chevron-right" class="size-5 rtl:-scale-x-100" /></button>
                </div>
                <p class="mt-2 hidden text-center text-[11px] text-muted md:block">{{ __('ui.keyboard_hint') }}</p>
                <p class="mt-2 text-center text-[11px] text-muted md:hidden">{{ __('ui.swipe_hint') }}</p>
            </div>

            {{-- Thumbnails strip --}}
            <div x-show="thumbsOpen" x-transition x-cloak @click.outside="thumbsOpen = false" class="absolute inset-x-0 bottom-full mb-2 px-3">
                <div x-ref="thumbs" class="scrollbar-none mx-auto flex max-w-4xl gap-2 overflow-x-auto rounded-2xl border border-border bg-surface/95 p-3 shadow-(--shadow-lift) backdrop-blur">
                    <template x-for="p in pages" :key="'t' + p.number">
                        <button type="button" @click="go(p.number)" :data-thumb="p.number"
                                class="group relative w-20 shrink-0 overflow-hidden rounded-lg border-2 transition"
                                :class="p.number === current ? 'border-primary' : 'border-transparent hover:border-border-strong'"
                                :aria-current="p.number === current ? 'page' : null" :aria-label="@js(__('ui.on_page', ['page' => ''])) + p.number">
                            <img :src="p.thumb" alt="" loading="lazy" class="w-full object-cover" :style="`aspect-ratio: ${p.ratio}`">
                            <span class="absolute inset-x-0 bottom-0 bg-black/60 py-0.5 text-center text-[11px] font-semibold text-white" x-text="p.number"></span>
                        </button>
                    </template>
                </div>
            </div>
        </footer>

        {{-- Selected offer sheet --}}
        <div x-show="selectedOffer" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-overlay" @click="selectedOffer = null"></div>
        <section x-show="selectedOffer" x-cloak x-transition:enter="transition duration-250 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 class="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-lg rounded-t-3xl bg-surface p-6 pb-[max(env(safe-area-inset-bottom),1.5rem)] shadow-(--shadow-lift)" role="dialog" aria-modal="true">
            <template x-if="selectedOffer">
                <div>
                    <div class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-surface-3"></div>
                    <x-badge tone="success" icon="check-badge" size="xs">{{ __('ui.verified_price') }}</x-badge>
                    <h2 class="mt-3 text-h3 font-semibold" x-text="selectedOffer.title"></h2>
                    <p class="text-sm text-muted" x-show="selectedOffer.brand" x-text="selectedOffer.brand"></p>
                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <span class="price text-3xl font-bold text-primary" dir="ltr" x-text="selectedOffer.price"></span>
                        <span class="price text-sm text-muted line-through" dir="ltr" x-show="selectedOffer.old_price" x-text="selectedOffer.old_price"></span>
                        <span class="rounded-full bg-accent px-2 py-0.5 text-xs font-semibold text-accent-fg" dir="ltr" x-show="selectedOffer.percent" x-text="'-' + selectedOffer.percent + '%'"></span>
                    </div>
                    <p class="mt-1 text-xs text-muted" x-show="selectedOffer.unit" x-text="selectedOffer.unit"></p>
                    <p class="mt-4 flex items-center gap-2 text-xs text-muted"><x-svg-icon name="shield-check" class="size-4 text-success" />{{ __('ui.verified_by_team') }}</p>
                    <div class="mt-5 flex gap-2">
                        <a :href="selectedOffer.url" class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-primary-fg hover:bg-primary-hover"><x-svg-icon name="scale" class="size-4" />{{ __('ui.details') }}</a>
                        <button type="button" @click="selectedOffer = null" class="inline-flex h-11 items-center justify-center rounded-full border border-border-strong px-5 text-sm font-semibold hover:bg-surface-2">{{ __('nav.close') }}</button>
                    </div>
                </div>
            </template>
        </section>

        {{-- Offers on this page --}}
        <div x-show="offersOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-overlay" @click="offersOpen = false"></div>
        <section x-show="offersOpen" x-cloak x-transition:enter="transition duration-250 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 class="fixed inset-x-0 bottom-0 z-50 mx-auto max-h-[75dvh] max-w-lg overflow-y-auto rounded-t-3xl bg-surface p-6 shadow-(--shadow-lift)" role="dialog" aria-modal="true" aria-labelledby="offers-title">
            <div class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-surface-3"></div>
            <h2 id="offers-title" class="text-h3 font-semibold">{{ __('ui.offers_on_page') }}</h2>
            <p class="mt-1 text-xs text-muted">{{ __('ui.tap_to_compare') }}</p>
            <ul class="mt-4 divide-y divide-border">
                <template x-for="offer in currentOffers" :key="'l' + offer.id">
                    <li>
                        <button type="button" @click="selectOffer(offer)" class="flex w-full items-center justify-between gap-3 py-3 text-start hover:text-primary">
                            <span class="min-w-0"><span class="block truncate text-sm font-medium" x-text="offer.title"></span><span class="block text-xs text-muted" x-text="offer.unit || offer.brand || ''"></span></span>
                            <span class="price shrink-0 font-bold text-primary" dir="ltr" x-text="offer.price"></span>
                        </button>
                    </li>
                </template>
            </ul>
            <p x-show="!currentOffers.length" class="py-6 text-center text-sm text-muted">{{ __('ui.no_results') }}</p>
        </section>

        {{-- Details sheet --}}
        <div x-show="detailsOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-overlay" @click="detailsOpen = false"></div>
        <aside x-show="detailsOpen" x-cloak x-transition:enter="transition duration-250 ease-out" x-transition:enter-start="ltr:translate-x-full rtl:-translate-x-full" x-transition:enter-end="translate-x-0"
               class="fixed inset-y-0 end-0 z-50 w-full max-w-md overflow-y-auto bg-surface p-6 shadow-(--shadow-lift)" role="dialog" aria-modal="true" aria-labelledby="details-title">
            <div class="flex items-center justify-between">
                <h2 id="details-title" class="text-h2 font-semibold">{{ __('ui.flyer_details') }}</h2>
                <button type="button" @click="detailsOpen = false" class="grid size-9 place-items-center rounded-full hover:bg-surface-2" aria-label="{{ __('nav.close') }}"><x-svg-icon name="x" class="size-5" /></button>
            </div>
            <div class="mt-6 flex items-center gap-3">
                <x-retailer-logo :retailer="$retailer" class="size-14" />
                <div>
                    <p class="font-semibold">{{ $retailer->name }}</p>
                    <p class="text-sm text-muted">{{ $retailer->type->label() }}</p>
                </div>
            </div>
            <dl class="mt-6 space-y-4 text-sm">
                <div><dt class="text-muted">{{ __('ui.details') }}</dt><dd class="mt-1 font-medium">{{ $flyer->title }}</dd></div>
                <div><dt class="text-muted">{{ __('ui.status') }}</dt><dd class="mt-1"><x-countdown-chip :countdown="$countdown" /></dd></div>
                <div><dt class="text-muted">{{ __('ui.valid_in_cities') }}</dt>
                    <dd class="mt-1 font-medium">{{ __('ui.valid_from_until', ['from' => fmt_date($flyer->valid_from), 'until' => fmt_date($flyer->valid_until, 'j F Y')]) }}</dd>
                    <dd class="mt-2 flex flex-wrap gap-1.5">@foreach ($flyer->cities as $c)<x-badge>{{ $c->name }}</x-badge>@endforeach</dd>
                </div>
                @if ($flyer->branches->isNotEmpty())
                    <div><dt class="text-muted">{{ __('ui.valid_in_branches') }}</dt>
                        <dd class="mt-2 space-y-2">
                            @foreach ($flyer->branches as $branch)
                                <div class="flex items-start gap-2"><x-svg-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-primary" /><span>{{ $branch->name }}<span class="block text-xs text-muted">{{ $branch->address }}</span></span></div>
                            @endforeach
                        </dd>
                    </div>
                @endif
            </dl>
            <div class="mt-8 grid gap-2">
                @if ($flyer->shop_online_url)<x-button :href="$flyer->shop_online_url" target="_blank" rel="noopener nofollow" icon="shopping-bag">{{ __('ui.shop_online') }}</x-button>@endif
                @if ($flyer->whatsapp_order_url)<x-button :href="$flyer->whatsapp_order_url" target="_blank" rel="noopener nofollow" variant="outline" icon="whatsapp">{{ __('ui.whatsapp_order') }}</x-button>@endif
                <x-button :href="route('stores.show', $retailer)" variant="secondary" icon="store">{{ __('ui.store_page') }}</x-button>
                <x-button variant="ghost" icon="flag" @click="detailsOpen = false; reportOpen = true">{{ __('ui.report') }}</x-button>
            </div>
            @if ($related->isNotEmpty())
                <h3 class="mt-10 mb-3 font-semibold">{{ __('ui.more_from', ['store' => $retailer->name]) }}</h3>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($related->take(4) as $other)
                        <a href="{{ $other->url() }}" class="card card-hover overflow-hidden">
                            @if ($other->coverPage())<img src="{{ $other->coverPage()->url(320) }}" alt="{{ $other->title }}" loading="lazy" class="w-full object-cover" style="aspect-ratio:3/4">@endif
                            <span class="block truncate p-2 text-xs font-medium">{{ $other->title }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </aside>

        {{-- Share fallback --}}
        <div x-show="shareOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-overlay" @click="shareOpen = false"></div>
        <section x-show="shareOpen" x-cloak x-transition class="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-md rounded-t-3xl bg-surface p-6 shadow-(--shadow-lift) md:inset-0 md:m-auto md:h-fit md:rounded-3xl" role="dialog" aria-modal="true" aria-labelledby="share-title">
            <h2 id="share-title" class="text-h3 font-semibold">{{ __('ui.share') }}</h2>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <a :href="shareLink('whatsapp')" target="_blank" rel="noopener" class="chip chip-idle justify-center py-3"><x-svg-icon name="whatsapp" class="size-4" />{{ __('ui.share_whatsapp') }}</a>
                <a :href="shareLink('x')" target="_blank" rel="noopener" class="chip chip-idle justify-center py-3"><x-svg-icon name="x-social" class="size-4" />{{ __('ui.share_x') }}</a>
                <a :href="shareLink('facebook')" target="_blank" rel="noopener" class="chip chip-idle justify-center py-3"><x-svg-icon name="facebook" class="size-4" />{{ __('ui.share_facebook') }}</a>
                <button type="button" @click="copy()" class="chip chip-idle justify-center py-3"><x-svg-icon name="link" class="size-4" /><span x-text="copied ? @js(__('ui.copied')) : @js(__('ui.copy_link'))"></span></button>
            </div>
        </section>

        {{-- Report --}}
        <div x-show="reportOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-overlay" @click="reportOpen = false"></div>
        <section x-show="reportOpen" x-cloak x-transition class="fixed inset-x-0 bottom-0 z-50 mx-auto max-w-md rounded-t-3xl bg-surface p-6 shadow-(--shadow-lift) md:inset-0 md:m-auto md:h-fit md:rounded-3xl" role="dialog" aria-modal="true" aria-labelledby="report-title">
            <h2 id="report-title" class="text-h3 font-semibold">{{ __('ui.report_title') }}</h2>
            <form method="POST" action="{{ route('flyers.report', $flyer) }}" class="mt-5 space-y-4">
                @csrf
                <fieldset>
                    <legend class="label">{{ __('ui.report_reason') }}</legend>
                    <div class="grid gap-2">
                        @foreach ($reportReasons as $reason)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-border px-3 py-2.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary-soft">
                                <input type="radio" name="reason" value="{{ $reason->value }}" required class="text-primary focus:ring-primary/20" @checked($loop->first)>
                                {{ $reason->label() }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-form.textarea name="details" :label="__('ui.report_details')" rows="3" />
                <div class="flex gap-2">
                    <x-button type="submit" class="flex-1">{{ __('ui.report_send') }}</x-button>
                    <x-button variant="outline" @click="reportOpen = false">{{ __('ui.cancel') }}</x-button>
                </div>
            </form>
        </section>

        <noscript>
            <div class="fixed inset-0 z-[60] overflow-y-auto bg-bg p-4">
                @foreach ($pages as $p)
                    <img src="{{ $p->url(1024) }}" alt="{{ $shareTitle }} – {{ __('ui.on_page', ['page' => $p->number]) }}" class="mx-auto mb-4 max-w-3xl w-full" loading="lazy">
                @endforeach
            </div>
        </noscript>
    </div>
</x-layouts.viewer>
