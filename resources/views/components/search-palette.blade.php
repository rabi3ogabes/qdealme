@php $trending = collect(config('trending', []))->take(6); @endphp
<div x-data="searchPalette({ suggestUrl: @js(route('search.suggest')), searchUrl: @js(route('search.index')) })"
     x-show="open" x-cloak class="fixed inset-0 z-[85]" role="dialog" aria-modal="true" aria-label="{{ __('nav.search') }}">
    <div class="absolute inset-0 bg-overlay" @click="hide()" x-transition.opacity></div>
    <div x-show="open" x-transition.origin.top x-trap.noscroll.inert="open" class="absolute inset-x-0 top-0 mx-auto flex max-h-dvh w-full max-w-2xl flex-col overflow-hidden bg-surface shadow-(--shadow-lift) md:top-[8vh] md:max-h-[80vh] md:rounded-3xl">
        <form @submit.prevent="submit()" class="flex items-center gap-3 border-b border-border px-4 py-3" role="search">
            <x-icon name="search" class="size-5 shrink-0 text-muted" />
            <input x-ref="input" x-model="query" @input="onInput()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.escape.prevent="hide()"
                   type="search" enterkeyhint="search" autocomplete="off" spellcheck="false"
                   class="h-11 flex-1 bg-transparent text-base outline-none placeholder:text-muted"
                   placeholder="{{ __('ui.search_placeholder') }}" aria-label="{{ __('nav.search') }}"
                   role="combobox" :aria-expanded="hasResults" aria-controls="search-results" aria-autocomplete="list">
            <span x-show="loading" class="size-4 animate-spin rounded-full border-2 border-border border-t-primary" aria-hidden="true"></span>
            <button type="button" @click="hide()" class="grid size-9 place-items-center rounded-full text-muted hover:bg-surface-2" aria-label="{{ __('nav.close') }}"><x-icon name="x" class="size-5" /></button>
        </form>

        <div id="search-results" class="flex-1 overflow-y-auto p-2" role="listbox">
            <template x-if="!hasResults && query.trim().length < 2">
                <div class="p-4">
                    <p class="mb-3 text-xs font-semibold tracking-wide text-muted uppercase">{{ __('ui.trending') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($trending as $t)
                            <button type="button" @click="query = @js($t[app()->getLocale()]); onInput(); fetch()" class="chip chip-idle">{{ $t[app()->getLocale()] }}</button>
                        @endforeach
                    </div>
                    <p class="mt-5 hidden text-xs text-muted md:block">{{ __('ui.search_shortcut') }}</p>
                </div>
            </template>

            <template x-if="isEmpty">
                <div class="p-8 text-center text-sm text-muted">{{ __('ui.no_results') }}</div>
            </template>

            @foreach (['retailers' => __('nav.stores'), 'flyers' => __('nav.offers'), 'offers' => __('nav.deals'), 'categories' => __('nav.categories')] as $group => $label)
                <template x-if="results.{{ $group }}.length">
                    <div class="mb-2">
                        <p class="px-3 pt-2 pb-1 text-xs font-semibold tracking-wide text-muted uppercase">{{ $label }}</p>
                        <template x-for="(item, i) in results.{{ $group }}" :key="item.url">
                            <a :href="item.url" :data-index="indexOf('{{ $group }}', i)" role="option" :aria-selected="active === indexOf('{{ $group }}', i)"
                               @mouseenter="active = indexOf('{{ $group }}', i)"
                               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors"
                               :class="active === indexOf('{{ $group }}', i) ? 'bg-primary-soft text-primary' : 'hover:bg-surface-2'">
                                <template x-if="item.image"><img :src="item.image" alt="" class="size-9 shrink-0 rounded-lg border border-border object-cover"></template>
                                <template x-if="!item.image"><span class="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-2 text-muted"><x-icon name="tag" class="size-4" /></span></template>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium" x-text="item.title"></span>
                                    <span class="block truncate text-xs text-muted" x-text="item.subtitle"></span>
                                </span>
                            </a>
                        </template>
                    </div>
                </template>
            @endforeach

            <template x-if="more && hasResults">
                <a :href="more" class="mt-1 flex items-center justify-center gap-2 rounded-xl border border-border px-3 py-3 text-sm font-semibold text-primary hover:bg-surface-2">
                    {{ __('ui.see_all') }} <x-icon name="arrow-right" class="size-4 rtl:-scale-x-100" />
                </a>
            </template>
        </div>
    </div>
</div>
