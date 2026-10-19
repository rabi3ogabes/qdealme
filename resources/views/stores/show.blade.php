<x-layouts.app :seo="$seo">
    <section class="border-b border-border bg-surface">
        <div class="container-x py-8 md:py-10">
            <nav class="mb-6 text-sm text-muted" aria-label="Breadcrumb">
                <a href="{{ route('offers.index') }}" class="hover:text-text">{{ __('nav.offers') }}</a>
                <span class="mx-1.5">/</span>
                <a href="{{ route('stores.index') }}" class="hover:text-text">{{ __('nav.stores') }}</a>
                <span class="mx-1.5">/</span>
                <span class="text-text" aria-current="page">{{ $retailer->name }}</span>
            </nav>
            <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-5">
                    <x-retailer-logo :retailer="$retailer" class="size-20 shadow-(--shadow-soft) md:size-24" />
                    <div>
                        <h1 class="text-h1 font-semibold">{{ $retailer->name }}</h1>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                            <x-badge>{{ $retailer->type->label() }}</x-badge>
                            @if ($retailer->verified_at)<x-badge tone="success" icon="check-badge">{{ __('offer.verification.verified') }}</x-badge>@endif
                            <span>{{ trans_choice('ui.live_flyers', $current->count(), ['count' => fmt_number($current->count())]) }}</span>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div x-data="toggleAction({ url: @js(route('retailers.follow', $retailer)), active: @js($isFollowing), authenticated: @js(auth()->check()), loginUrl: @js(route('login')) })" data-error-message="{{ __('ui.error_500_title') }}">
                        <button type="button" @click="toggle()" :disabled="busy"
                                class="inline-flex h-11 items-center gap-2 rounded-full px-5 text-sm font-semibold transition"
                                :class="active ? 'bg-primary-soft text-primary' : 'bg-primary text-primary-fg hover:bg-primary-hover'" :aria-pressed="active">
                            <x-svg-icon name="heart" class="size-4" x-bind:class="active ? 'fill-current' : ''" />
                            <span x-text="active ? @js(__('ui.following')) : @js(__('ui.follow'))"></span>
                        </button>
                    </div>
                    @if ($retailer->website_url)<x-button :href="$retailer->website_url" target="_blank" rel="noopener nofollow" variant="outline" icon="globe">{{ __('ui.website') }}</x-button>@endif
                    @if ($retailer->whatsappUrl())<x-button :href="$retailer->whatsappUrl()" target="_blank" rel="noopener nofollow" variant="outline" icon="whatsapp">{{ __('ui.whatsapp') }}</x-button>@endif
                </div>
            </div>
            @if ($retailer->about)<p class="mt-6 max-w-3xl text-sm leading-7 text-text-2">{{ $retailer->about }}</p>@endif
        </div>
    </section>

    <div class="container-x space-y-14 py-10">
        <section aria-labelledby="current-title">
            <x-section-heading :title="__('ui.current_flyers_from', ['store' => $retailer->name])" />
            @if ($current->isEmpty())
                <x-empty-state :title="__('ui.no_live_flyers')" :text="__('ui.no_live_flyers_text')" icon="calendar" />
            @else
                <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($current as $flyer)<x-flyer-card :flyer="$flyer" :today="$today" :eager="$loop->index < 4" />@endforeach
                </div>
            @endif
        </section>

        @if ($offers->isNotEmpty())
            <section>
                <x-section-heading :title="__('ui.offers_in_flyer')" :action-href="route('deals.index', ['store' => $retailer->slug])" />
                <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($offers as $offer)<x-offer-card :offer="$offer" />@endforeach
                </div>
            </section>
        @endif

        @if ($upcoming->isNotEmpty())
            <section>
                <x-section-heading :title="__('ui.upcoming')" />
                <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($upcoming as $flyer)
                        <div class="card p-4">
                            <p class="font-semibold">{{ $flyer->title }}</p>
                            <p class="mt-1 text-sm text-muted">{{ __('ui.valid_from_until', ['from' => fmt_date($flyer->valid_from), 'until' => fmt_date($flyer->valid_until)]) }}</p>
                            <x-countdown-chip :countdown="$flyer->countdown($today)" class="mt-3" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($branches->isNotEmpty())
            <section>
                <x-section-heading :title="__('ui.branches_in', ['city' => $city->name])" />
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($branches as $branch)
                        <div class="card p-5">
                            <p class="flex items-center gap-2 font-semibold"><x-svg-icon name="map-pin" class="size-4 text-primary" />{{ $branch->name }}</p>
                            @if ($branch->address)<p class="mt-2 text-sm text-text-2">{{ $branch->address }}</p>@endif
                            @if ($branch->opening_hours)
                                <p class="mt-2 flex items-center gap-2 text-sm text-muted"><x-svg-icon name="clock" class="size-4" />{{ $branch->opening_hours[app()->getLocale()] ?? ($branch->opening_hours['en'] ?? '') }}</p>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($branch->phone)<a href="tel:{{ $branch->phone }}" class="chip chip-idle" dir="ltr"><x-svg-icon name="phone" class="size-4" />{{ $branch->phone }}</a>@endif
                                @if ($branch->mapUrl())<a href="{{ $branch->mapUrl() }}" target="_blank" rel="noopener" class="chip chip-idle"><x-svg-icon name="map-pin" class="size-4" />{{ __('ui.map') }}</a>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($expiredPreview->isNotEmpty())
            <section>
                <x-section-heading :title="__('ui.expired_archive')" :action-href="route('stores.archive', $retailer)" :action-label="__('ui.view_archive', ['count' => fmt_number($expiredCount)])" />
                <div class="grid grid-cols-3 gap-3 opacity-80 sm:grid-cols-4 lg:grid-cols-8">
                    @foreach ($expiredPreview as $flyer)
                        <a href="{{ $flyer->url() }}" class="card overflow-hidden grayscale transition hover:grayscale-0" rel="nofollow">
                            @if ($flyer->coverPage())<img src="{{ $flyer->coverPage()->url(320) }}" alt="{{ $flyer->title }}" loading="lazy" class="w-full object-cover" style="aspect-ratio:3/4">@endif
                            <span class="block truncate p-2 text-[11px] text-muted">{{ fmt_date($flyer->valid_until) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
