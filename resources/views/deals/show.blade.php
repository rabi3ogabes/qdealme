<x-layouts.app :seo="$seo">
    @php
        $page = $offer->page;
        $bbox = $offer->bbox ?? ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1];
    @endphp
    <div class="container-x py-8">
        <nav class="mb-6 text-sm text-muted" aria-label="Breadcrumb">
            <a href="{{ route('deals.index') }}" class="hover:text-text">{{ __('nav.deals') }}</a>
            @if ($offer->category)
                <span class="mx-1.5">/</span><a href="{{ route('deals.category', $offer->category) }}" class="hover:text-text">{{ $offer->category->name }}</a>
            @endif
        </nav>

        @if ($expired)
            <div class="mb-6 rounded-2xl border border-warning/30 bg-warning-soft px-4 py-3 text-sm text-warning" role="status">
                <strong>{{ __('ui.error_410_title') }}.</strong> {{ __('ui.error_410_text') }}
                <a class="font-semibold underline" href="{{ route('stores.show', $retailer) }}">{{ __('ui.current_flyers_from', ['store' => $retailer->name]) }}</a>
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-2">
            <div class="card relative overflow-hidden bg-surface-2">
                <div class="relative mx-auto" style="aspect-ratio: {{ $page->aspectRatio() }}; max-height: 70vh">
                    <img src="{{ $page->url(1024) }}" @if ($page->srcset()) srcset="{{ $page->srcset() }}" sizes="(min-width:1024px) 45vw, 100vw" @endif alt="{{ $offer->title }}" class="h-full w-full object-contain">
                    <span class="pointer-events-none absolute rounded-lg border-2 border-accent shadow-(--shadow-glow)" style="left:{{ $bbox['x'] * 100 }}%;top:{{ $bbox['y'] * 100 }}%;width:{{ $bbox['w'] * 100 }}%;height:{{ $bbox['h'] * 100 }}%"></span>
                </div>
            </div>

            <div>
                <a href="{{ route('stores.show', $retailer) }}" class="inline-flex items-center gap-2 text-sm font-medium text-text-2 hover:text-text"><x-retailer-logo :retailer="$retailer" class="size-8" />{{ $retailer->name }}</a>
                <h1 class="mt-4 text-h1 font-semibold text-balance">{{ $offer->title }}</h1>
                @if ($offer->brand)<p class="mt-1 text-muted">{{ $offer->brand }}</p>@endif

                <div class="card mt-6 p-6">
                    <x-badge tone="success" icon="check-badge">{{ __('ui.verified_price') }}</x-badge>
                    <x-price-block :offer="$offer" size="lg" class="mt-4" />
                    @if ($offer->savingAmount())
                        <p class="mt-3 text-sm font-semibold text-success">{{ __('ui.you_save', ['amount' => fmt_price($offer->savingAmount()), 'percent' => fmt_number($offer->savingPercent())]) }}</p>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-muted">
                        <x-countdown-chip :countdown="$countdown" />
                        <span>{{ __('ui.valid_until', ['date' => fmt_date($flyer->valid_until, 'j F Y')]) }}</span>
                    </div>
                    <p class="mt-4 flex items-center gap-2 text-xs text-muted"><x-svg-icon name="shield-check" class="size-4 text-success" />{{ __('ui.verified_by_team') }}</p>
                    <div class="mt-6 flex flex-wrap gap-2">
                        <x-button :href="$offer->flyerUrl()" icon="document">{{ __('ui.go_to_flyer') }}</x-button>
                        @if ($flyer->shop_online_url)<x-button :href="$flyer->shop_online_url" target="_blank" rel="noopener nofollow" variant="outline" icon="shopping-bag">{{ __('ui.shop_online') }}</x-button>@endif
                    </div>
                </div>

                @if ($branches->isNotEmpty())
                    <div class="mt-6">
                        <h2 class="mb-3 font-semibold">{{ __('ui.valid_in_branches') }}</h2>
                        <ul class="space-y-2 text-sm">
                            @foreach ($branches as $branch)
                                <li class="flex items-start gap-2"><x-svg-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-primary" /><span>{{ $branch->name }} <span class="block text-xs text-muted">{{ $branch->address }}</span></span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        @if ($similar->isNotEmpty())
            <section class="mt-14">
                <x-section-heading :title="__('ui.similar_deals')" :action-href="$offer->category ? route('deals.category', $offer->category) : route('deals.index')" />
                <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
                    @foreach ($similar as $item)<x-offer-card :offer="$item" />@endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
