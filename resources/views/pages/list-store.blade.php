<x-layouts.app :seo="$seo">
    <div class="container-x grid gap-10 py-12 lg:grid-cols-[1fr_1.2fr]">
        <div>
            <x-badge tone="accent" icon="sparkles">{{ __('nav.for_retailers') }}</x-badge>
            <h1 class="mt-4 text-h1 font-semibold text-balance">{{ __('pages.list_store.heading') }}</h1>
            <p class="mt-3 text-text-2">{{ __('pages.list_store.text') }}</p>
            <ul class="mt-8 space-y-4">
                @foreach (['map-pin', 'scale', 'sparkles'] as $i => $icon)
                    <li class="flex gap-4">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary"><x-svg-icon :name="$icon" class="size-5" /></span>
                        <span><span class="block font-semibold">{{ __('pages.list_store.benefit_'.($i + 1).'_title') }}</span><span class="block text-sm text-muted">{{ __('pages.list_store.benefit_'.($i + 1).'_text') }}</span></span>
                    </li>
                @endforeach
            </ul>
        </div>
        <form method="POST" action="{{ route('pages.list-store.send') }}" class="card space-y-5 p-6 md:p-8">
            @csrf
            <x-flash />
            <div class="hidden" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <x-form.input name="company_name" :label="__('pages.list_store.company')" required autocomplete="organization" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="contact_name" :label="__('pages.list_store.contact')" required autocomplete="name" />
                <x-form.input name="email" type="email" :label="__('pages.list_store.email')" required autocomplete="email" dir="ltr" />
                <x-form.input name="phone" type="tel" :label="__('pages.list_store.phone')" autocomplete="tel" dir="ltr" />
                <x-form.select name="city_id" :label="__('pages.list_store.city')" :options="$cities->pluck('name', 'id')->all()" :placeholder="'—'" />
            </div>
            <x-form.select name="retailer_type" :label="__('pages.list_store.type')" :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" :placeholder="'—'" />
            <x-form.textarea name="message" :label="__('pages.list_store.message')" rows="4" />
            <x-button type="submit" size="lg">{{ __('pages.list_store.send') }}</x-button>
        </form>
    </div>
</x-layouts.app>
