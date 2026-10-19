<x-layouts.app :seo="$seo">
    <div class="container-x max-w-3xl py-10">
        <h1 class="text-h1 font-semibold">{{ __('account.title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ __('account.member_since', ['date' => fmt_date($user->created_at, 'F Y')]) }}</p>
        <div class="mt-6"><x-flash /></div>

        <form method="POST" action="{{ route('account.update') }}" class="card space-y-5 p-6 md:p-8">
            @csrf
            @method('PATCH')
            <h2 class="text-h3 font-semibold">{{ __('account.profile') }}</h2>
            <x-form.input name="name" :label="__('auth.name')" :value="$user->name" required />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.select name="locale" :label="__('account.language')" :options="collect(config('qdealme.locales'))->map(fn ($l) => $l['name'])->all()" :value="$user->locale" required />
                <x-form.select name="city_id" :label="__('account.city')" :options="$cities->pluck('name', 'id')->all()" :value="$user->city_id" placeholder="—" />
            </div>
            <x-form.select name="digits" :label="__('account.digits')" :options="['latin' => '0123456789', 'arabic' => '٠١٢٣٤٥٦٧٨٩']" :value="$user->digits" required />
            <p class="-mt-3 text-xs text-muted">{{ __('account.digits_help') }}</p>
            <x-form.checkbox name="marketing_opt_in" :label="__('account.marketing')" :checked="$user->marketing_opt_in" />
            <x-button type="submit">{{ __('account.save_changes') }}</x-button>
        </form>

        <form method="POST" action="{{ route('account.destroy') }}" class="card mt-8 space-y-4 border-danger/30 p-6 md:p-8" x-data="{ confirm: false }">
            @csrf
            @method('DELETE')
            <h2 class="text-h3 font-semibold text-danger">{{ __('account.delete_account') }}</h2>
            <p class="text-sm text-muted">{{ __('account.delete_warning') }}</p>
            <x-form.input name="password" type="password" :label="__('account.confirm_password')" required autocomplete="current-password" dir="ltr" />
            <x-button type="submit" variant="danger" icon="trash">{{ __('account.delete_account') }}</x-button>
        </form>
    </div>
</x-layouts.app>
