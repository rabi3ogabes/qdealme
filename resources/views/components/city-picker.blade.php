@php $city = current_city(); @endphp
@if ($city)
<div x-data="sheet()" @qd:open-city.window="show()" x-show="open" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true" aria-labelledby="city-picker-title">
    <div class="absolute inset-0 bg-overlay" @click="hide()" x-transition.opacity></div>
    <div x-show="open" x-transition.origin.bottom x-trap.noscroll.inert="open" class="absolute inset-x-0 bottom-0 mx-auto max-h-[85dvh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-surface p-6 shadow-(--shadow-lift) md:inset-0 md:m-auto md:h-fit md:rounded-3xl">
        <div class="mb-5 flex items-center justify-between">
            <h2 id="city-picker-title" class="text-h2 font-semibold">{{ __('nav.choose_city') }}</h2>
            <button type="button" @click="hide()" class="grid size-9 place-items-center rounded-full hover:bg-surface-2" aria-label="{{ __('nav.close') }}"><x-icon name="x" class="size-5" /></button>
        </div>
        <p class="mb-4 text-sm text-muted">{{ __('nav.shopping_in', ['city' => $city->country->name]) }}</p>
        <ul class="grid grid-cols-2 gap-2">
            @foreach ($navCities ?? [] as $option)
                <li>
                    <a href="{{ city_url($option) }}" @class(['flex items-center gap-2 rounded-xl border px-3.5 py-3 text-sm font-medium transition-colors', 'border-primary bg-primary-soft text-primary' => $option->is($city), 'border-border hover:border-border-strong hover:bg-surface-2' => ! $option->is($city)]) @if ($option->is($city)) aria-current="true" @endif>
                        <x-icon name="map-pin" class="size-4 shrink-0" />
                        <span class="truncate">{{ $option->name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif
