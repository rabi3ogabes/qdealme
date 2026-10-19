@props(['title', 'subtitle' => null, 'actionHref' => null, 'actionLabel' => null, 'level' => 'h2'])
<div {{ $attributes->merge(['class' => 'mb-5 flex items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        <{{ $level }} class="text-h2 font-semibold tracking-tight text-balance">{{ $title }}</{{ $level }}>
        @if ($subtitle)<p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>@endif
    </div>
    @if ($actionHref)
        <a href="{{ $actionHref }}" class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-primary hover:underline">
            {{ $actionLabel ?? __('ui.view_all') }}
            <x-icon name="arrow-right" class="size-4 rtl:-scale-x-100" />
        </a>
    @endif
    {{ $slot }}
</div>
