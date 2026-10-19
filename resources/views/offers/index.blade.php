<x-layouts.app :seo="$seo">
    @php
        $today = local_today($city);
    @endphp

    {{-- Hero --}}
    <section class="relative overflow-hidden border-b border-border">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60%_80%_at_85%_0%,var(--color-ink-100),transparent_60%),radial-gradient(40%_60%_at_0%_100%,var(--color-gold-50),transparent_60%)] opacity-90 dark:opacity-20"></div>
        <div class="container-x py-8 md:py-12">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl animate-fade-up">
                    <p class="inline-flex items-center gap-2 rounded-full border border-border bg-surface/70 px-3 py-1 text-xs font-semibold text-primary backdrop-blur">
                        <x-svg-icon name="sparkles" class="size-3.5 text-accent" />
                        {{ __('brand.slogan') }}
                    </p>
                    <h1 class="mt-4 text-display font-semibold text-balance">
                        @if ($store)
                            {{ __('seo.store_offers_title', ['store' => $store->name, 'city' => $city->name]) }}
                        @else
                            {{ __('brand.hero_title', ['city' => $city->name]) }}
                        @endif
                    </h1>
                    <p class="mt-3 max-w-xl text-base text-text-2 text-pretty">{{ __('brand.hero_subtitle', ['city' => $city->name]) }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" x-data @click="$dispatch('qd:open-city')" class="chip chip-idle h-11 px-4">
                        <x-svg-icon name="map-pin" class="size-4 text-primary" />
                        {{ __('nav.shopping_in', ['city' => $city->name]) }}
                        <span class="text-primary">· {{ __('nav.change_city') }}</span>
                    </button>
                    <x-button :href="route('pages.list-store')" variant="outline" icon="plus">{{ __('ui.add_company') }}</x-button>
                </div>
            </div>

            {{-- Quick filters --}}
            <nav class="scrollbar-none -mx-4 mt-8 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="{{ __('ui.quick_filters') }}">
                <a href="{{ route('offers.index', array_filter(['sort' => $listing->sort === 'featured' ? null : $listing->sort])) }}"
                   @class(['chip', 'chip-active' => $listing->type === null && ! $listing->store, 'chip-idle' => $listing->type !== null || $listing->store])>
                    {{ __('ui.filter_all') }} <span class="opacity-70">{{ fmt_number($typeCounts['all'] ?? 0) }}</span>
                </a>
                @foreach ($typeCounts as $key => $count)
                    @continue($key === 'all')
                    <a href="{{ route('offers.index', array_filter(['type' => $key, 'sort' => $listing->sort === 'featured' ? null : $listing->sort])) }}"
                       @class(['chip', 'chip-active' => $listing->type === $key, 'chip-idle' => $listing->type !== $key])>
                        {{ __('ui.filter_'.$key) }} <span class="opacity-70">{{ fmt_number($count) }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    <div class="container-x py-8">
        {{-- Retailer logo strip --}}
        @if ($retailers->isNotEmpty())
            <section x-data="logoStrip()" class="card relative mb-8 p-4 md:p-5" aria-labelledby="strip-title">
                <div class="mb-3 flex items-center justify-between">
                    <h2 id="strip-title" class="text-sm font-semibold text-text-2">{{ __('ui.logo_strip_title') }}</h2>
                    <div class="flex gap-1">
                        <button type="button" @click="scroll(-1)" :disabled="!canBack" class="grid size-8 place-items-center rounded-full border border-border bg-surface disabled:opacity-40" aria-label="{{ __('ui.prev_page') }}"><x-svg-icon name="chevron-left" class="size-4 rtl:-scale-x-100" /></button>
                        <button type="button" @click="scroll(1)" :disabled="!canForward" class="grid size-8 place-items-center rounded-full border border-border bg-surface disabled:opacity-40" aria-label="{{ __('ui.next_page') }}"><x-svg-icon name="chevron-right" class="size-4 rtl:-scale-x-100" /></button>
                    </div>
                </div>
                <div x-ref="track" class="scrollbar-none flex snap-x gap-3 overflow-x-auto pb-1">
                    @foreach ($retailers as $retailer)
                        <x-retailer-chip :retailer="$retailer" :active="$store?->is($retailer)" />
                    @endforeach
                </div>
            </section>
        @endif

        <div class="grid gap-8 lg:grid-cols-[260px_1fr]">
            <aside class="hidden lg:block">
                <div class="sticky top-24 space-y-4">
                    <x-category-sidebar :categories="$categories" />
                    <div class="card overflow-hidden bg-gradient-to-br from-ink-800 to-ink-950 p-5 text-white">
                        <x-svg-icon name="store" class="size-6 text-gold-300" />
                        <p class="mt-3 font-semibold">{{ __('ui.add_company') }}</p>
                        <p class="mt-1 text-sm text-white/75">{{ __('ui.add_company_text') }}</p>
                        <x-button :href="route('pages.list-store')" variant="accent" size="sm" class="mt-4">{{ __('nav.list_store') }}</x-button>
                    </div>
                </div>
            </aside>

            <section aria-labelledby="flyers-title" class="min-w-0">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="flyers-title" class="text-h2 font-semibold">{{ __('ui.current_flyers') }}</h2>
                        <p class="mt-0.5 text-sm text-muted">{{ trans_choice('ui.flyers_count', $flyers->total(), ['count' => fmt_number($flyers->total())]) }}</p>
                    </div>
                    <div class="inline-flex rounded-full border border-border bg-surface p-1" role="tablist" aria-label="{{ __('ui.sort') }}">
                        @foreach (['featured' => 'sparkles', 'latest' => 'clock', 'ending' => 'bolt'] as $sortKey => $sortIcon)
                            <a href="{{ route('offers.index', array_filter(['type' => $listing->type, 'store' => $listing->store, 'sort' => $sortKey === 'featured' ? null : $sortKey])) }}"
                               role="tab" aria-selected="{{ $listing->sort === $sortKey ? 'true' : 'false' }}"
                               @class(['inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors', 'bg-primary text-primary-fg shadow-(--shadow-soft)' => $listing->sort === $sortKey, 'text-text-2 hover:text-text' => $listing->sort !== $sortKey])>
                                <x-svg-icon :name="$sortIcon" class="size-4" />{{ __('ui.sort_'.$sortKey) }}
                            </a>
                        @endforeach
                    </div>
                </div>

                @if ($flyers->isEmpty())
                    <x-empty-state :title="__('ui.no_live_flyers')" :text="__('ui.no_live_flyers_text')" icon="calendar" :action-href="route('offers.index')" :action-label="__('ui.filter_all')" />
                @else
                    <div x-data="loadMore({ nextUrl: @js($flyers->nextPageUrl()) })">
                        <div x-ref="grid" class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4">
                            @foreach ($flyers as $flyer)
                                <x-flyer-card :flyer="$flyer" :today="$today" :eager="$loop->index < 4" />
                            @endforeach
                        </div>
                        <div x-ref="sentinel" class="h-1"></div>
                        <div x-show="loading" x-cloak class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4">
                            @for ($i = 0; $i < 4; $i++)<x-skeleton-card />@endfor
                        </div>
                        <noscript>{{ $flyers->links('vendor.pagination.qdealme') }}</noscript>
                        <div class="mt-8 flex justify-center" x-show="nextUrl && !loading" x-cloak>
                            <x-button variant="outline" @click="load()">{{ __('ui.load_more') }}</x-button>
                        </div>
                    </div>
                @endif
            </section>
        </div>

        {{-- Trust section --}}
        <section class="mt-16" aria-labelledby="trust-title">
            <h2 id="trust-title" class="text-h2 font-semibold">{{ __('ui.trust_title', ['brand' => brand()]) }}</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['eye-slash', 'check-badge', 'language', 'shield-check'] as $i => $icon)
                    <div class="card p-5">
                        <span class="grid size-11 place-items-center rounded-xl bg-primary-soft text-primary"><x-svg-icon :name="$icon" class="size-5" /></span>
                        <h3 class="mt-4 font-semibold">{{ __('ui.trust_'.($i + 1).'_title') }}</h3>
                        <p class="mt-1.5 text-sm text-muted">{{ __('ui.trust_'.($i + 1).'_text') }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($city->intro)
            <section class="card mt-10 p-6 md:p-8">
                <h2 class="text-h3 font-semibold">{{ __('ui.home_intro_title', ['city' => $city->name]) }}</h2>
                <p class="mt-2 text-sm leading-7 text-text-2">{{ $city->intro }}</p>
            </section>
        @endif
    </div>
</x-layouts.app>
