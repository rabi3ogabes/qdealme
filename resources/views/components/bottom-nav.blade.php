@php
    $items = [
        ['href' => route('offers.index'), 'icon' => 'home', 'label' => __('nav.offers'), 'active' => request()->routeIs('offers.*')],
        ['href' => route('stores.index'), 'icon' => 'store', 'label' => __('nav.stores'), 'active' => request()->routeIs('stores.*')],
        ['search' => true, 'icon' => 'search', 'label' => __('nav.search'), 'active' => request()->routeIs('search.*')],
        ['href' => route('deals.index'), 'icon' => 'tag', 'label' => __('nav.deals'), 'active' => request()->routeIs('deals.*')],
        ['href' => auth()->check() ? route('account.saved') : route('login'), 'icon' => auth()->check() ? 'bookmark' : 'user', 'label' => auth()->check() ? __('nav.saved') : __('nav.account'), 'active' => request()->routeIs('account*', 'login', 'register')],
    ];
@endphp
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl md:hidden" aria-label="{{ __('nav.menu') }}">
    <ul class="grid grid-cols-5">
        @foreach ($items as $item)
            <li>
                @if (! empty($item['search']))
                    <button type="button" x-data @click="$dispatch('qd:open-search')" class="flex w-full flex-col items-center gap-1 px-1 py-2.5 text-[11px] font-medium text-muted">
                        <span class="grid size-9 place-items-center rounded-full bg-primary text-primary-fg shadow-(--shadow-soft)"><x-svg-icon name="search" class="size-5" /></span>
                        <span>{{ $item['label'] }}</span>
                    </button>
                @else
                    <a href="{{ $item['href'] }}" @class(['flex flex-col items-center gap-1 px-1 py-2.5 text-[11px] font-medium', 'text-primary' => $item['active'], 'text-muted' => ! $item['active']]) @if ($item['active']) aria-current="page" @endif>
                        <span @class(['grid size-9 place-items-center rounded-full transition-colors', 'bg-primary-soft' => $item['active']])><x-svg-icon :name="$item['icon']" class="size-5" /></span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
