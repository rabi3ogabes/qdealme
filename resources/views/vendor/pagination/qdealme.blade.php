@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="mt-8 flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="chip chip-idle opacity-50" aria-disabled="true">{!! __('pagination.previous') !!}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="chip chip-idle">{!! __('pagination.previous') !!}</a>
        @endif

        <ul class="hidden items-center gap-1 sm:flex">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="px-2 text-muted">{{ $element }}</li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="grid size-9 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-fg" aria-current="page">{{ fmt_number($page) }}</span>
                            @else
                                <a href="{{ $url }}" class="grid size-9 place-items-center rounded-full text-sm font-medium hover:bg-surface-2">{{ fmt_number($page) }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
        </ul>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="chip chip-idle">{!! __('pagination.next') !!}</a>
        @else
            <span class="chip chip-idle opacity-50" aria-disabled="true">{!! __('pagination.next') !!}</span>
        @endif
    </nav>
@endif
