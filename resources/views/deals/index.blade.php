<x-layouts.app :seo="$seo">
    @php
        $base = array_filter(['q' => $q ?: null, 'store' => $store?->slug, 'min' => $min, 'max' => $max, 'sort' => $sort === 'best' ? null : $sort], fn ($v) => $v !== null && $v !== '');
        $routeFor = fn (array $params) => $category ? route('deals.category', array_merge(['category' => $category], $params)) : route('deals.index', $params);
    @endphp
    <div class="container-x py-8">
        <div class="mb-6">
            <h1 class="text-h1 font-semibold">{{ $title }}</h1>
            <p class="mt-1 text-sm text-muted">{{ trans_choice('ui.products_count', $offers->total(), ['count' => fmt_number($offers->total())]) }} · <span class="inline-flex items-center gap-1 text-success"><x-svg-icon name="check-badge" class="size-4" />{{ __('ui.verified_price') }}</span></p>
        </div>

        <div class="grid gap-8 lg:grid-cols-[260px_1fr]">
            <aside class="hidden lg:block"><div class="sticky top-24"><x-category-sidebar :categories="$categories" :current="$category" /></div></aside>

            <section x-data="sheet()" class="min-w-0">
                <div class="mb-5 flex flex-wrap items-center gap-2">
                    <button type="button" @click="show()" class="chip chip-idle h-10"><x-svg-icon name="adjustments" class="size-4" />{{ __('ui.show_filters') }}</button>
                    <form method="GET" class="ms-auto" x-data>
                        @foreach (array_diff_key($base, ['sort' => 1]) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                        <label class="sr-only" for="sort">{{ __('ui.sort') }}</label>
                        <select id="sort" name="sort" @change="$el.form.submit()" class="input h-10 py-0 pe-9 text-sm">
                            @foreach (\App\Http\Controllers\DealsController::SORTS as $s)
                                <option value="{{ $s }}" @selected($sort === $s)>{{ __('ui.sort_'.$s) }}</option>
                            @endforeach
                        </select>
                    </form>
                    @if ($store)
                        <a href="{{ $routeFor(array_diff_key($base, ['store' => 1])) }}" class="chip chip-active"><x-retailer-logo :retailer="$store" class="size-5" />{{ $store->name }} <x-svg-icon name="x" class="size-3.5" /></a>
                    @endif
                    @if ($q)
                        <a href="{{ $routeFor(array_diff_key($base, ['q' => 1])) }}" class="chip chip-active">“{{ $q }}” <x-svg-icon name="x" class="size-3.5" /></a>
                    @endif
                </div>

                @if ($stores->isNotEmpty())
                    <div class="scrollbar-none -mx-4 mb-6 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="{{ __('ui.filter_by_store') }}">
                        @foreach ($stores as $s)
                            <a href="{{ $routeFor(array_merge($base, ['store' => $s->slug])) }}" @class(['chip', 'chip-active' => $store?->is($s), 'chip-idle' => ! $store?->is($s)])>
                                <x-retailer-logo :retailer="$s" class="size-5" />{{ $s->name }} <span class="opacity-60">{{ fmt_number($storeCounts[$s->id] ?? 0) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($offers->isEmpty())
                    <x-empty-state :title="__('ui.empty_title')" :text="__('ui.empty_text')" icon="tag" :action-href="route('deals.index')" :action-label="__('ui.clear')" />
                @else
                    <div x-data="loadMore({ nextUrl: @js($offers->nextPageUrl()) })">
                        <div x-ref="grid" class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-4">
                            @foreach ($offers as $offer)<x-offer-card :offer="$offer" />@endforeach
                        </div>
                        <div x-ref="sentinel" class="h-1"></div>
                        <noscript>{{ $offers->links('vendor.pagination.qdealme') }}</noscript>
                        <div class="mt-8 flex justify-center" x-show="nextUrl" x-cloak>
                            <x-button variant="outline" @click="load()" x-bind:disabled="loading"><span x-text="loading ? @js(__('ui.loading')) : @js(__('ui.load_more'))"></span></x-button>
                        </div>
                    </div>
                @endif

                {{-- Filter sheet --}}
                <div x-show="open" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-labelledby="filters-title">
                    <div class="absolute inset-0 bg-overlay" @click="hide()" x-transition.opacity></div>
                    <form method="GET" action="{{ $routeFor([]) }}" x-show="open" x-transition x-trap.noscroll="open" class="absolute inset-x-0 bottom-0 mx-auto max-h-[85dvh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-surface p-6 shadow-(--shadow-lift) md:inset-0 md:m-auto md:h-fit md:rounded-3xl">
                        <div class="mb-5 flex items-center justify-between">
                            <h2 id="filters-title" class="text-h2 font-semibold">{{ __('ui.show_filters') }}</h2>
                            <button type="button" @click="hide()" class="grid size-9 place-items-center rounded-full hover:bg-surface-2" aria-label="{{ __('nav.close') }}"><x-svg-icon name="x" class="size-5" /></button>
                        </div>
                        <div class="space-y-5">
                            <x-form.input name="q" :label="__('nav.search')" :value="$q" type="search" />
                            <div>
                                <p class="label">{{ __('ui.price_range') }} ({{ __('currency.QAR') }})</p>
                                <div class="grid grid-cols-2 gap-3">
                                    <input type="number" name="min" min="0" step="1" value="{{ $min }}" placeholder="{{ __('ui.min') }}" class="input" aria-label="{{ __('ui.min') }}">
                                    <input type="number" name="max" min="0" step="1" value="{{ $max }}" placeholder="{{ fmt_number(ceil($priceCeiling)) }}" class="input" aria-label="{{ __('ui.max') }}">
                                </div>
                            </div>
                            <x-form.select name="store" :label="__('ui.store')" :options="$stores->pluck('name', 'slug')->all()" :value="$store?->slug" :placeholder="__('ui.all')" />
                            <x-form.select name="sort" :label="__('ui.sort')" :options="collect(\App\Http\Controllers\DealsController::SORTS)->mapWithKeys(fn ($s) => [$s => __('ui.sort_'.$s)])->all()" :value="$sort" required />
                            <div class="lg:hidden">
                                <p class="label">{{ __('nav.categories') }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($categories as $root)
                                        <a href="{{ route('deals.category', $root) }}" @class(['chip', 'chip-active' => $category?->id === $root->id, 'chip-idle' => $category?->id !== $root->id])>{{ $root->name }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex gap-2">
                            <x-button type="submit" class="flex-1">{{ __('ui.apply') }}</x-button>
                            <x-button :href="$routeFor([])" variant="outline">{{ __('ui.clear') }}</x-button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
