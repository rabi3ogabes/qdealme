@php
    $city = current_city();
    $user = auth()->user();
    $links = [
        ['route' => 'offers.index', 'label' => __('nav.offers'), 'active' => request()->routeIs('offers.*', 'flyers.*')],
        ['route' => 'stores.index', 'label' => __('nav.stores'), 'active' => request()->routeIs('stores.*')],
        ['route' => 'deals.index', 'label' => __('nav.deals'), 'active' => request()->routeIs('deals.*')],
    ];
@endphp
<header class="sticky top-0 z-40 border-b border-border bg-bg/85 backdrop-blur-xl supports-[backdrop-filter]:bg-bg/70">
    <div class="container-x flex h-16 items-center gap-3 md:h-[72px]">
        <a href="{{ route('offers.index') }}" class="flex shrink-0 items-center gap-2" aria-label="{{ brand() }}">
            <x-logo class="h-8 w-auto md:h-9" />
        </a>

        @if ($city)
            <button type="button"
                    class="chip chip-idle hidden sm:inline-flex"
                    x-data
                    @click="$dispatch('qd:open-city')"
                    aria-haspopup="dialog">
                <x-icon name="map-pin" class="size-4 text-primary" />
                <span class="font-semibold">{{ $city->name }}</span>
                <x-icon name="chevron-down" class="size-3.5 text-muted" />
            </button>
        @endif

        <button type="button"
                x-data
                @click="$dispatch('qd:open-search')"
                class="mx-auto hidden h-11 w-full max-w-xl items-center gap-3 rounded-full border border-border bg-surface px-4 text-start text-sm text-muted shadow-(--shadow-soft) transition hover:border-border-strong md:flex">
            <x-icon name="search" class="size-4 shrink-0" />
            <span class="flex-1 truncate">{{ __('ui.search_placeholder') }}</span>
            <kbd class="hidden rounded-md border border-border bg-surface-2 px-1.5 py-0.5 font-sans text-[11px] text-muted lg:inline">⌘K</kbd>
        </button>

        <nav class="ms-auto hidden items-center gap-1 lg:flex" aria-label="{{ __('nav.menu') }}">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}"
                   @class(['rounded-full px-3.5 py-2 text-sm font-medium transition-colors', 'bg-primary-soft text-primary' => $link['active'], 'text-text-2 hover:text-text hover:bg-surface-2' => ! $link['active']])
                   @if ($link['active']) aria-current="page" @endif>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="ms-auto flex items-center gap-1 lg:ms-2">
            <button type="button" x-data @click="$dispatch('qd:open-search')" class="grid size-10 place-items-center rounded-full text-text-2 hover:bg-surface-2 md:hidden" aria-label="{{ __('nav.search') }}">
                <x-icon name="search" class="size-5" />
            </button>

            <a href="{{ localized_url(other_locale()) }}"
               class="chip chip-idle h-10 px-3 text-xs font-semibold tracking-wide uppercase"
               hreflang="{{ other_locale() }}"
               lang="{{ other_locale() }}"
               aria-label="{{ __('ui.language_toggle') }}">
                <x-icon name="language" class="size-4" />
                <span>{{ config('qdealme.locales.'.other_locale().'.short') }}</span>
            </a>

            <x-theme-toggle />

            @auth
                <a href="{{ route('account') }}" class="chip chip-idle hidden h-10 sm:inline-flex" aria-label="{{ __('nav.account') }}">
                    <x-icon name="user" class="size-4" />
                    <span class="max-w-[8rem] truncate">{{ $user->name }}</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="chip hidden h-10 bg-primary text-primary-fg hover:bg-primary-hover sm:inline-flex">
                    <x-icon name="user" class="size-4" />
                    <span>{{ __('nav.login') }}</span>
                </a>
            @endauth

            <div x-data="sheet()" class="relative">
                <button type="button" @click="toggle()" class="grid size-10 place-items-center rounded-full border border-border bg-surface text-text-2 hover:bg-surface-2" :aria-expanded="open" aria-label="{{ __('nav.open_menu') }}">
                    <x-icon name="menu" class="size-5" x-show="!open" />
                    <x-icon name="x" class="size-5" x-show="open" x-cloak />
                </button>
                <div x-show="open" x-cloak x-transition.origin.top.end @click.outside="hide()" class="absolute end-0 top-12 z-50 w-64 rounded-2xl border border-border bg-surface p-2 shadow-(--shadow-lift)">
                    <div class="px-3 py-2 lg:hidden">
                        @foreach ($links as $link)
                            <a href="{{ route($link['route']) }}" class="block rounded-lg px-2 py-2 text-sm font-medium hover:bg-surface-2">{{ $link['label'] }}</a>
                        @endforeach
                        <div class="hairline my-2"></div>
                    </div>
                    <a href="{{ route('pages.list-store') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="store" class="size-4 text-primary" />{{ __('nav.list_store') }}</a>
                    <a href="{{ route('pages.about') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="info" class="size-4 text-muted" />{{ __('nav.about') }}</a>
                    <a href="{{ route('pages.contact') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="mail" class="size-4 text-muted" />{{ __('nav.contact') }}</a>
                    @if ($city)
                        <button type="button" @click="hide(); $dispatch('qd:open-city')" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm hover:bg-surface-2 sm:hidden"><x-icon name="map-pin" class="size-4 text-muted" />{{ __('nav.shopping_in', ['city' => $city->name]) }} · {{ __('nav.change_city') }}</button>
                    @endif
                    <div class="hairline my-2"></div>
                    <form method="POST" action="{{ route('preferences.digits') }}" class="flex items-center justify-between px-3 py-2 text-sm">
                        @csrf
                        <span class="text-muted">{{ __('nav.digits') }}</span>
                        <span class="inline-flex overflow-hidden rounded-lg border border-border">
                            <button type="submit" name="digits" value="latin" @class(['px-2.5 py-1 text-xs font-semibold', 'bg-primary text-primary-fg' => digit_style() === 'latin'])>{{ __('nav.digits_latin') }}</button>
                            <button type="submit" name="digits" value="arabic" @class(['px-2.5 py-1 text-xs font-semibold', 'bg-primary text-primary-fg' => digit_style() === 'arabic'])>{{ __('nav.digits_arabic') }}</button>
                        </span>
                    </form>
                    <div class="hairline my-2"></div>
                    @auth
                        <a href="{{ route('account.saved') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="bookmark" class="size-4 text-muted" />{{ __('nav.saved') }}</a>
                        <a href="{{ route('account') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="user" class="size-4 text-muted" />{{ __('nav.account') }}</a>
                        @if ($user->isStaff())
                            <a href="{{ url('/admin') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="shield-check" class="size-4 text-muted" />{{ __('nav.admin') }}</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm text-danger hover:bg-danger-soft"><x-icon name="logout" class="size-4" />{{ __('nav.logout') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="user" class="size-4 text-muted" />{{ __('nav.login') }}</a>
                        <a href="{{ route('register') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2"><x-icon name="plus" class="size-4 text-muted" />{{ __('nav.register') }}</a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</header>
