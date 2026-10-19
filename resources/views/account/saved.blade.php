<x-layouts.app :seo="$seo">
    @php $today = local_today(current_city()); @endphp
    <div class="container-x py-10">
        <h1 class="text-h1 font-semibold">{{ __('account.saved') }}</h1>
        <section class="mt-8">
            <h2 class="mb-4 text-h3 font-semibold">{{ __('account.followed_stores') }}</h2>
            @if ($follows->isEmpty())
                <p class="text-sm text-muted">{{ __('account.no_follows') }}</p>
            @else
                <div class="scrollbar-none flex gap-3 overflow-x-auto pb-2">@foreach ($follows as $r)<x-retailer-chip :retailer="$r" />@endforeach</div>
            @endif
        </section>
        <section class="mt-10">
            <h2 class="mb-4 text-h3 font-semibold">{{ __('account.saved_flyers') }}</h2>
            @if ($favorites->isEmpty())
                <x-empty-state :title="__('account.no_saved')" icon="bookmark" :action-href="route('offers.index')" :action-label="__('ui.back_home')" />
            @else
                <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">@foreach ($favorites as $f)<x-flyer-card :flyer="$f" :today="$today" />@endforeach</div>
            @endif
        </section>
    </div>
</x-layouts.app>
