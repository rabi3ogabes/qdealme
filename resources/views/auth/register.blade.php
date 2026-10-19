<x-layouts.app :seo="$seo">
    <div class="container-x flex justify-center py-12 md:py-20">
        <div class="card w-full max-w-md p-6 md:p-8">
            <h1 class="text-h1 font-semibold">{{ __('auth.register_title', ['brand' => brand()]) }}</h1>
            <p class="mt-2 text-sm text-muted">{{ __('auth.register_text') }}</p>
            <form method="POST" action="{{ route('register.attempt') }}" class="mt-8 space-y-5">
                @csrf
                <x-form.input name="name" :label="__('auth.name')" required autocomplete="name" autofocus />
                <x-form.input name="email" type="email" :label="__('auth.email')" required autocomplete="email" dir="ltr" />
                <x-form.input name="password" type="password" :label="__('auth.password_label')" required autocomplete="new-password" dir="ltr" />
                <x-form.input name="password_confirmation" type="password" :label="__('auth.password_confirmation')" required autocomplete="new-password" dir="ltr" />
                <x-form.checkbox name="marketing_opt_in" :label="__('auth.marketing_opt_in')" />
                <x-button type="submit" size="lg" class="w-full">{{ __('auth.create_account') }}</x-button>
            </form>
            <p class="mt-6 text-center text-sm text-muted">{{ __('auth.have_account') }} <a href="{{ route('login') }}" class="font-semibold text-primary">{{ __('auth.sign_in') }}</a></p>
        </div>
    </div>
</x-layouts.app>
