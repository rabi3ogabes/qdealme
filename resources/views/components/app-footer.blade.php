<footer class="mt-16 border-t border-border bg-surface">
    <div class="container-x py-12">
        <div class="grid gap-10 md:grid-cols-12">
            <div class="md:col-span-5">
                <x-logo class="h-9 w-auto" />
                <p class="mt-4 max-w-md text-sm leading-6 text-text-2">{{ __('brand.footer_tagline') }}</p>
                <p class="mt-5 inline-flex items-center gap-2 rounded-full bg-accent-soft px-3 py-1.5 text-sm font-semibold text-accent-fg">
                    <x-svg-icon name="sparkles" class="size-4" />
                    {{ __('brand.slogan') }}
                </p>
            </div>
            <div class="md:col-span-2">
                <h2 class="text-sm font-semibold">{{ __('nav.for_shoppers') }}</h2>
                <ul class="mt-3 space-y-2 text-sm text-text-2">
                    <li><a class="hover:text-text" href="{{ route('offers.index') }}">{{ __('nav.offers') }}</a></li>
                    <li><a class="hover:text-text" href="{{ route('stores.index') }}">{{ __('nav.stores') }}</a></li>
                    <li><a class="hover:text-text" href="{{ route('deals.index') }}">{{ __('nav.deals') }}</a></li>
                    <li><a class="hover:text-text" href="{{ route('search.index') }}">{{ __('nav.search') }}</a></li>
                </ul>
            </div>
            <div class="md:col-span-2">
                <h2 class="text-sm font-semibold">{{ __('nav.for_retailers') }}</h2>
                <ul class="mt-3 space-y-2 text-sm text-text-2">
                    <li><a class="hover:text-text" href="{{ route('pages.list-store') }}">{{ __('nav.list_store') }}</a></li>
                    <li><a class="hover:text-text" href="{{ route('pages.contact') }}">{{ __('nav.contact') }}</a></li>
                </ul>
            </div>
            <div class="md:col-span-3">
                <h2 class="text-sm font-semibold">{{ __('nav.company') }}</h2>
                <ul class="mt-3 space-y-2 text-sm text-text-2">
                    <li><a class="hover:text-text" href="{{ route('pages.about') }}">{{ __('nav.about') }}</a></li>
                    <li><a class="hover:text-text" href="{{ route('pages.privacy') }}">{{ __('nav.privacy') }}</a></li>
                    <li><a class="hover:text-text" href="{{ route('pages.terms') }}">{{ __('nav.terms') }}</a></li>
                    <li><a class="hover:text-text" href="{{ localized_url(other_locale()) }}" hreflang="{{ other_locale() }}">{{ config('qdealme.locales.'.other_locale().'.name') }}</a></li>
                </ul>
            </div>
        </div>
        <div class="hairline mt-10 flex flex-col gap-3 pt-6 text-xs text-muted sm:flex-row sm:items-center sm:justify-between">
            <p>{{ __('brand.rights', ['year' => fmt_number(now()->year), 'brand' => brand()]) }}</p>
            <p class="inline-flex items-center gap-1.5"><x-svg-icon name="heart" class="size-3.5 text-primary" /> {{ __('brand.made_in') }}</p>
        </div>
    </div>
</footer>
