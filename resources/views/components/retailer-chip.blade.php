@props(['retailer', 'active' => false, 'count' => null])
<a href="{{ route('stores.show', $retailer) }}"
   {{ $attributes->merge(['class' => 'group flex w-[88px] shrink-0 snap-start flex-col items-center gap-2 text-center']) }}
   title="{{ $retailer->name }}">
    <span @class(['grid size-16 place-items-center rounded-2xl border bg-white p-1.5 shadow-(--shadow-soft) transition-[transform,box-shadow] duration-200 ease-(--ease-out-soft) group-hover:-translate-y-0.5 group-hover:shadow-(--shadow-lift)', 'border-primary ring-2 ring-primary/20' => $active, 'border-border' => ! $active])>
        @if ($retailer->logoUrl())
            <img src="{{ $retailer->logoUrl() }}" alt="" width="64" height="64" loading="lazy" decoding="async" class="h-full w-full rounded-xl object-cover">
        @else
            <span class="text-lg font-bold text-ink-800">{{ mb_substr($retailer->name_en, 0, 1) }}</span>
        @endif
    </span>
    <span class="line-clamp-2 text-[11px] font-medium leading-tight text-text-2 group-hover:text-text">{{ $retailer->name }}</span>
</a>
