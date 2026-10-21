@props(['offer', 'width' => 640])
@php
    // Shows only the offer's region of the flyer page. The page image is a
    // background sized and positioned so that the bbox fills the box exactly.
    $page = $offer->page;
    $b = $offer->bbox ?? [];
    $x = min(max((float) ($b['x'] ?? 0), 0), 1);
    $y = min(max((float) ($b['y'] ?? 0), 0), 1);
    $w = min(max((float) ($b['w'] ?? 1), 0.02), 1 - $x ?: 1);
    $h = min(max((float) ($b['h'] ?? 1), 0.02), 1 - $y ?: 1);
    $ratio = round(($w * ($page->width ?: 1080)) / ($h * ($page->height ?: 1440)), 4);
    $posX = $w >= 1 ? 0 : $x / (1 - $w) * 100;
    $posY = $h >= 1 ? 0 : $y / (1 - $h) * 100;
    $style = sprintf(
        "background-image:url('%s');background-size:%.4f%% %.4f%%;background-position:%.4f%% %.4f%%;aspect-ratio:%s;width:min(100cqw, calc(100cqh * %s))",
        e($page->url($width, 'jpg')), 100 / $w, 100 / $h, $posX, $posY, $ratio, $ratio
    );
@endphp
<div {{ $attributes->merge(['class' => 'grid h-full w-full place-items-center overflow-hidden']) }} style="container-type: size">
    <div role="img" aria-label="{{ $offer->title }}" class="max-h-full max-w-full rounded-lg bg-no-repeat shadow-(--shadow-soft)" style="{{ $style }}"></div>
</div>
