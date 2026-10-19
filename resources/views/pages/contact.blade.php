<x-layouts.app :seo="$seo">
    <div class="container-x grid gap-10 py-12 lg:grid-cols-[1fr_1.2fr]">
        <div>
            <h1 class="text-h1 font-semibold">{{ __('pages.contact.heading') }}</h1>
            <p class="mt-3 text-text-2">{{ __('pages.contact.text') }}</p>
            <p class="mt-8 text-sm text-muted">{{ __('pages.contact.email_us') }}</p>
            <a href="mailto:{{ brand('support_email') }}" class="mt-1 inline-flex items-center gap-2 text-lg font-semibold text-primary" dir="ltr"><x-svg-icon name="mail" class="size-5" />{{ brand('support_email') }}</a>
        </div>
        <form method="POST" action="{{ route('pages.contact.send') }}" class="card space-y-5 p-6 md:p-8">
            @csrf
            <x-flash />
            <div class="hidden" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" :label="__('pages.contact.name')" required autocomplete="name" />
                <x-form.input name="email" type="email" :label="__('pages.contact.email')" required autocomplete="email" dir="ltr" />
            </div>
            <x-form.input name="subject" :label="__('pages.contact.subject')" />
            <x-form.textarea name="message" :label="__('pages.contact.message')" rows="6" required />
            <x-button type="submit" size="lg">{{ __('pages.contact.send') }}</x-button>
        </form>
    </div>
</x-layouts.app>
