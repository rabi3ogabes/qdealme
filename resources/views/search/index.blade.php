<x-layouts.app :seo="$seo">
    @php $today = local_today(current_city()); $total = $retailers->count() + $flyers->count() + $offers->count(); @endphp
    <div class="container-x py-8">
        <form method="GET" action="{{ route('search.index') }}" role="search" class="card flex items-center gap-3 p-2 ps-4">
            <x-svg-icon name="search" class="size-5 shrink-0 text-muted" />
            <label for="q" class="sr-only">{{ __('nav.search') }}</label>
            <input id="q" name="q" type="search" value="{{ $q }}" placeholder="{{ __('ui.search_placeholder') }}" class="h-11 flex-1 bg-transparent text-base outline-none placeholder:text-muted" autofocus>
            <x-button type="submit">{{ __('ui.search_button') }}</x-button>
        </form>

        @if ($q === '' || mb_strlen($q) < 2)
            <div class="mt-8">
                <h1 class="text-h2 font-semibold">{{ __('ui.trending') }}</h1>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($trending as $t)<a href="{{ route('search.index', ['q' => $t[app()->getLocale()]]) }}" class="chip chip-idle">{{ $t[app()->getLocale()] }}</a>@endforeach
                </div>
            </div>
        @else
            <h1 class="mt-8 text-h2 font-semibold">{{ __('ui.results_for', ['count' => fmt_number($total), 'query' => $q]) }}</h1>
            @if ($total === 0)
                <x-empty-state class="mt-6" :title="__('ui.no_results')" :text="__('ui.search_hint')" icon="search" />
            @endif
            @if ($retailers->isNotEmpty())
                <section class="mt-8">
                    <h2 class="mb-3 text-h3 font-semibold">{{ __('nav.stores') }}</h2>
                    <div class="scrollbar-none flex gap-3 overflow-x-auto pb-2">@foreach ($retailers as $r)<x-retailer-chip :retailer="$r" />@endforeach</div>
                </section>
            @endif
            @if ($flyers->isNotEmpty())
                <section class="mt-8">
                    <h2 class="mb-3 text-h3 font-semibold">{{ __('nav.offers') }}</h2>
                    <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">@foreach ($flyers as $f)<x-flyer-card :flyer="$f" :today="$today" />@endforeach</div>
                </section>
            @endif
            @if ($offers->isNotEmpty())
                <section class="mt-8">
                    <h2 class="mb-3 text-h3 font-semibold">{{ __('nav.deals') }}</h2>
                    <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">@foreach ($offers as $o)<x-offer-card :offer="$o" />@endforeach</div>
                </section>
            @endif
        @endif
    </div>
</x-layouts.app>
