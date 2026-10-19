<div x-data="theme()" class="relative">
    <button type="button" @click="cycle()" class="grid size-10 place-items-center rounded-full border border-border bg-surface text-text-2 hover:bg-surface-2"
            :aria-label="'{{ __('ui.theme_toggle') }}: ' + setting"
            :title="'{{ __('nav.theme') }}: ' + ({auto: '{{ __('nav.theme_auto') }}', light: '{{ __('nav.theme_light') }}', dark: '{{ __('nav.theme_dark') }}'}[setting])">
        <x-svg-icon name="computer" class="size-5" x-show="setting === 'auto'" />
        <x-svg-icon name="sun" class="size-5" x-show="setting === 'light'" x-cloak />
        <x-svg-icon name="moon" class="size-5" x-show="setting === 'dark'" x-cloak />
    </button>
</div>
