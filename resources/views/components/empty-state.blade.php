@props(['title', 'text' => null, 'icon' => 'sparkles', 'actionHref' => null, 'actionLabel' => null])
<div {{ $attributes->merge(['class' => 'card flex flex-col items-center px-6 py-14 text-center']) }}>
    <span class="grid size-16 place-items-center rounded-full bg-primary-soft text-primary"><x-icon :name="$icon" class="size-7" /></span>
    <h3 class="mt-5 text-h3 font-semibold">{{ $title }}</h3>
    @if ($text)<p class="mt-2 max-w-md text-sm text-muted">{{ $text }}</p>@endif
    @if ($actionHref)
        <x-button :href="$actionHref" variant="outline" class="mt-6">{{ $actionLabel }}</x-button>
    @endif
    {{ $slot }}
</div>
