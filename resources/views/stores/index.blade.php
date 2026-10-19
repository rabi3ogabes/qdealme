<x-layouts.app :seo="$seo">
    <div class="container-x py-10">
        <x-section-heading :title="__('seo.stores_title', ['city' => $city->name])" :subtitle="__('seo.stores_description', ['city' => $city->name, 'brand' => brand()])" level="h1" />
        @forelse ($groups as $key => $retailers)
            <section class="mt-10" aria-labelledby="group-{{ $key }}">
                <h2 id="group-{{ $key }}" class="mb-4 text-h3 font-semibold">{{ __('ui.filter_'.$key) }} <span class="text-sm font-normal text-muted">· {{ trans_choice('ui.stores_count', $retailers->count(), ['count' => fmt_number($retailers->count())]) }}</span></h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($retailers as $retailer)
                        <a href="{{ route('stores.show', $retailer) }}" class="card card-hover flex items-center gap-4 p-4">
                            <x-retailer-logo :retailer="$retailer" class="size-14" />
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $retailer->name }}</span>
                                <span class="block text-sm text-muted">{{ $retailer->type->label() }}</span>
                                <span @class(['mt-1 inline-flex text-xs font-semibold', 'text-success' => $retailer->live_flyers_count > 0, 'text-muted' => $retailer->live_flyers_count === 0])>{{ trans_choice('ui.live_flyers', $retailer->live_flyers_count, ['count' => fmt_number($retailer->live_flyers_count)]) }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <x-empty-state :title="__('ui.empty_title')" :text="__('ui.empty_text')" icon="store" />
        @endforelse
    </div>
</x-layouts.app>
