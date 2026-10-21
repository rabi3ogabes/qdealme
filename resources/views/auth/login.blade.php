<x-layouts.app :seo="$seo">
    <div class="container-x flex justify-center py-12 md:py-20">
        <div class="card w-full max-w-md p-6 md:p-8">
            <h1 class="text-h1 font-semibold">{{ __('auth.login_title') }}</h1>
            <p class="mt-2 text-sm text-muted">{{ __('auth.login_text') }}</p>
            <form method="POST" action="{{ route('login.attempt') }}" class="mt-8 space-y-5">
                @csrf
                <x-form.input name="email" type="email" :label="__('auth.email')" required autocomplete="email" dir="ltr" autofocus />
                <x-form.input name="password" type="password" :label="__('auth.password_label')" required autocomplete="current-password" dir="ltr" />
                <label class="flex items-center gap-3 text-sm text-text-2"><input type="checkbox" name="remember" value="1" class="size-5 rounded-md border-border-strong text-primary">{{ __('auth.remember') }}</label>
                <x-button type="submit" size="lg" class="w-full">{{ __('auth.sign_in') }}</x-button>
            </form>
            <p class="mt-6 text-center text-sm text-muted">{{ __('auth.no_account') }} <a href="{{ route('register') }}" class="font-semibold text-primary">{{ __('auth.create_account') }}</a></p>
        </div>
    </div>
</x-layouts.app>
