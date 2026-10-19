<x-layouts.app :seo="$seo">
    <section class="relative overflow-hidden border-b border-border">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60%_80%_at_80%_0%,var(--color-ink-100),transparent_60%)] dark:opacity-20"></div>
        <div class="container-x py-16 md:py-24">
            <p class="text-sm font-semibold text-primary">{{ __('pages.about.title', ['brand' => brand()]) }}</p>
            <h1 class="mt-3 max-w-3xl text-display font-semibold text-balance">{{ __('pages.about.heading') }}</h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-text-2">{{ __('pages.about.intro') }}</p>
        </div>
    </section>
    <div class="container-x py-14">
        <h2 class="text-h2 font-semibold">{{ __('pages.about.mission_title') }}</h2>
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @foreach (['shield-check', 'check-badge', 'eye-slash', 'language'] as $i => $icon)
                <div class="card flex gap-4 p-6">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary"><x-svg-icon :name="$icon" class="size-5" /></span>
                    <p class="text-text-2">{{ __('pages.about.mission_'.($i + 1)) }}</p>
                </div>
            @endforeach
        </div>
        <div class="card mt-12 flex flex-col items-start gap-5 bg-gradient-to-br from-ink-800 to-ink-950 p-8 text-white md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-h2 font-semibold">{{ __('pages.about.retailers_title') }}</h2>
                <p class="mt-2 max-w-2xl text-white/80">{{ __('pages.about.retailers_text') }}</p>
            </div>
            <x-button :href="route('pages.list-store')" variant="accent" size="lg">{{ __('pages.about.cta') }}</x-button>
        </div>
    </div>
</x-layouts.app>
