@props(['categories', 'current' => null])
<nav {{ $attributes->merge(['class' => 'card p-3']) }} aria-label="{{ __('nav.categories') }}">
    <h2 class="px-3 pt-1 pb-2 text-sm font-semibold tracking-wide text-muted uppercase">{{ __('nav.categories') }}</h2>
    <a href="{{ route('deals.index') }}" @class(['flex items-center justify-between rounded-xl px-3 py-2 text-sm font-medium', 'bg-primary-soft text-primary' => $current === null && request()->routeIs('deals.index'), 'hover:bg-surface-2' => ! ($current === null && request()->routeIs('deals.index'))])>
        {{ __('ui.all_categories') }}
    </a>
    @foreach ($categories as $root)
        @php $open = $current && ($current->id === $root->id || $current->parent_id === $root->id); @endphp
        <div x-data="{ open: @js($open || $loop->first) }" class="mt-1">
            <div class="flex items-center">
                <a href="{{ route('deals.category', $root) }}" @class(['flex-1 rounded-xl px-3 py-2 text-sm font-semibold', 'bg-primary-soft text-primary' => $current?->id === $root->id, 'hover:bg-surface-2' => $current?->id !== $root->id])>{{ $root->name }}</a>
                @if ($root->children->isNotEmpty())
                    <button type="button" @click="open = !open" class="grid size-9 place-items-center rounded-lg text-muted hover:bg-surface-2" :aria-expanded="open" aria-label="{{ $root->name }}">
                        <x-icon name="chevron-down" class="size-4 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                    </button>
                @endif
            </div>
            @if ($root->children->isNotEmpty())
                <ul x-show="open" x-collapse class="ms-3 border-s border-border ps-2">
                    @foreach ($root->children as $child)
                        <li>
                            <a href="{{ route('deals.category', $child) }}" @class(['block rounded-lg px-3 py-1.5 text-sm', 'bg-primary-soft font-semibold text-primary' => $current?->id === $child->id, 'text-text-2 hover:bg-surface-2 hover:text-text' => $current?->id !== $child->id])>{{ $child->name }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</nav>
