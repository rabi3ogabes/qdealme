<?php

namespace App\Http\Controllers;

use App\Enums\ReportReason;
use App\Models\Flyer;
use App\Models\Retailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FlyersController extends Controller
{
    public function show(Request $request, Retailer $retailer, string $flyer): Response|RedirectResponse
    {
        $id = Flyer::idFromSegment($flyer);

        abort_if($id === null, 404);

        $model = Flyer::query()
            ->public()
            ->whereKey($id)
            ->where('retailer_id', $retailer->id)
            ->with(['retailer', 'pages', 'cities', 'branches.city', 'cover'])
            ->firstOrFail();

        if ($model->routeSegment() !== $flyer) {
            return redirect()->to($model->url((int) $request->query('page', 1)), 301);
        }

        $pageCount = max(1, $model->pages->count());
        $page = (int) $request->query('page', 1);

        if ($page < 1 || $page > $pageCount) {
            return redirect()->to($model->url(min(max($page, 1), $pageCount)), 302);
        }

        $offers = $model->offers()
            ->verified()
            ->with(['page', 'category'])
            ->get()
            ->groupBy('flyer_page_id');

        $related = Flyer::query()
            ->published()
            ->forCity(current_city())
            ->where('retailer_id', $retailer->id)
            ->whereKeyNot($model->id)
            ->with(['retailer', 'cover'])
            ->latest()
            ->limit(6)
            ->get();

        if (! $request->boolean('preview')) {
            Flyer::query()->whereKey($model->id)->increment('view_count');
        }

        $isFavorite = $request->user()?->favorites()->whereKey($model->id)->exists() ?? false;
        $isFollowing = $request->user()?->follows()->whereKey($retailer->id)->exists() ?? false;

        $title = __('seo.flyer_title', [
            'store' => $retailer->name,
            'title' => $model->title,
            'city' => $this->cityName(),
        ]);

        $validity = __('seo.valid_range', [
            'from' => fmt_date($model->valid_from),
            'until' => fmt_date($model->valid_until, 'j F Y'),
        ]);

        $seo = $this->seo()
            ->title($title)
            ->description(__('seo.flyer_description', [
                'store' => $retailer->name,
                'title' => $model->title,
                'pages' => fmt_number($pageCount),
                'validity' => $validity,
                'city' => $this->cityName(),
            ]))
            ->canonical($model->url())
            ->image($model->coverPage()?->url(1024, 'jpg'))
            ->type('article')
            ->noindex($model->isExpired())
            ->breadcrumbs([
                __('nav.offers') => route('offers.index'),
                $retailer->name => route('stores.show', $retailer),
                $model->title => $model->url(),
            ])
            ->jsonLd([
                '@type' => 'ItemList',
                'name' => $title,
                'numberOfItems' => $offers->flatten()->count(),
                'itemListElement' => $offers->flatten()->values()->map(fn ($offer, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'item' => [
                        '@type' => 'Product',
                        'name' => $offer->title,
                        'brand' => $offer->brand ? ['@type' => 'Brand', 'name' => $offer->brand] : null,
                        'offers' => [
                            '@type' => 'Offer',
                            'price' => (string) $offer->price,
                            'priceCurrency' => $offer->currency,
                            'priceValidUntil' => $model->valid_until->toDateString(),
                            'availability' => 'https://schema.org/InStock',
                            'url' => route('deals.show', ['offer' => $offer->routeSegment()]),
                        ],
                    ],
                ])->all(),
            ]);

        $response = response()->view('flyers.show', [
            'seo' => $seo,
            'flyer' => $model,
            'retailer' => $retailer,
            'pages' => $model->pages,
            'currentPage' => $page,
            'offersByPage' => $offers,
            'highlightOffer' => (int) $request->query('offer', 0),
            'related' => $related,
            'isFavorite' => $isFavorite,
            'isFollowing' => $isFollowing,
            'reportReasons' => ReportReason::cases(),
            'countdown' => $model->countdown(local_today(current_city())),
        ]);

        if ($model->isExpired()) {
            $response->setStatusCode(410);
        }

        return $response;
    }
}
