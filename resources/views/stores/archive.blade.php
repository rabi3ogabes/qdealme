<x-layouts.app :seo="$seo">
    <div class="container-x py-10">
        <a href="{{ route('stores.show', $retailer) }}" class="mb-6 inline-flex items-center gap-2 text-sm text-muted hover:text-text"><x-svg-icon name="arrow-left" class="size-4 rtl:-scale-x-100" />{{ $retailer->name }}</a>
        <x-section-heading :title="__('seo.store_archive_title', ['store' => $retailer->name, 'city' => current_city()->name])" level="h1" />
        @if ($flyers->isEmpty())
            <x-empty-state :title="__('ui.empty_title')" :text="__('ui.empty_text')" />
        @else
            <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-6">
                @foreach ($flyers as $flyer)
                    <a href="{{ $flyer->url() }}" class="card card-hover overflow-hidden" rel="nofollow">
                        @if ($flyer->coverPage())<img src="{{ $flyer->coverPage()->url(320) }}" alt="{{ $flyer->title }}" loading="lazy" class="w-full object-cover grayscale" style="aspect-ratio:3/4">@endif
                        <div class="p-3">
                            <p class="truncate text-sm font-medium">{{ $flyer->title }}</p>
                            <p class="mt-1 text-xs text-muted">{{ __('ui.valid_from_until', ['from' => fmt_date($flyer->valid_from), 'until' => fmt_date($flyer->valid_until, 'j F Y')]) }}</p>
                            <x-badge tone="muted" size="xs" class="mt-2">{{ __('ui.expired') }}</x-badge>
                        </div>
                    </a>
                @endforeach
            </div>
            {{ $flyers->links('vendor.pagination.qdealme') }}
        @endif
    </div>
</x-layouts.app>
