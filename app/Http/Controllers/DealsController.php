<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Offer;
use App\Models\Retailer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DealsController extends Controller
{
    public const SORTS = ['best', 'price_asc', 'price_desc', 'saving', 'newest', 'ending'];

    public function index(Request $request): View
    {
        return $this->listing($request, null);
    }

    public function category(Request $request, Category $category): View
    {
        return $this->listing($request, $category);
    }

    public function show(Request $request, string $offer): Response|RedirectResponse
    {
        $id = Offer::idFromSegment($offer);
        abort_if($id === null, 404);

        $model = Offer::query()
            ->verified()
            ->whereKey($id)
            ->whereHas('flyer', fn (Builder $q) => $q->public())
            ->with(['flyer.retailer', 'flyer.cities', 'page', 'retailer.branches.city', 'category.parent'])
            ->firstOrFail();

        if ($model->routeSegment() !== $offer) {
            return redirect()->route('deals.show', ['offer' => $model->routeSegment()], 301);
        }

        $expired = $model->flyer->isExpired();

        $similar = Offer::query()->live()
            ->whereKeyNot($model->id)
            ->when($model->category_id, fn (Builder $q) => $q->where('category_id', $model->category_id))
            ->whereHas('flyer', fn (Builder $q) => $q->forCity(current_city()))
            ->with(['flyer.retailer', 'page', 'retailer'])
            ->orderBy('price')
            ->limit(8)
            ->get();

        $branches = $model->retailer->branches->where('city_id', current_city()->id)->where('is_active', true);

        $seo = $this->seo()
            ->title(__('seo.offer_title', ['title' => $model->title, 'store' => $model->retailer->name, 'price' => fmt_price($model->price)]))
            ->description(__('seo.offer_description', [
                'title' => $model->title,
                'store' => $model->retailer->name,
                'price' => fmt_price($model->price),
                'until' => fmt_date($model->flyer->valid_until, 'j F Y'),
                'city' => $this->cityName(),
            ]))
            ->image($model->page->url(1024, 'jpg'))
            ->type('product')
            ->noindex($expired)
            ->breadcrumbs(array_filter([
                __('nav.deals') => route('deals.index'),
                $model->category?->name => $model->category ? route('deals.category', $model->category) : null,
                $model->title => route('deals.show', ['offer' => $model->routeSegment()]),
            ]))
            ->jsonLd(array_filter([
                '@type' => 'Product',
                'name' => $model->title,
                'brand' => $model->brand ? ['@type' => 'Brand', 'name' => $model->brand] : null,
                'image' => $model->page->url(1024, 'jpg'),
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) $model->price,
                    'priceCurrency' => $model->currency,
                    'priceValidUntil' => $model->flyer->valid_until->toDateString(),
                    'availability' => $expired ? 'https://schema.org/Discontinued' : 'https://schema.org/InStock',
                    'seller' => ['@type' => 'Organization', 'name' => $model->retailer->name],
                    'url' => route('deals.show', ['offer' => $model->routeSegment()]),
                ],
            ]));

        $response = response()->view('deals.show', [
            'seo' => $seo,
            'offer' => $model,
            'flyer' => $model->flyer,
            'retailer' => $model->retailer,
            'similar' => $similar,
            'branches' => $branches,
            'expired' => $expired,
            'countdown' => $model->flyer->countdown(local_today(current_city())),
        ]);

        return $expired ? $response->setStatusCode(410) : $response;
    }

    private function listing(Request $request, ?Category $category): View
    {
        $city = current_city();
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'best';
        $store = $request->filled('store') ? Retailer::query()->where('slug', $request->query('store'))->first() : null;
        $q = trim((string) $request->query('q', ''));
        $min = $request->filled('min') ? max(0, (float) $request->query('min')) : null;
        $max = $request->filled('max') ? max(0, (float) $request->query('max')) : null;

        $query = Offer::query()
            ->live()
            ->whereHas('flyer', fn (Builder $f) => $f->forCity($city))
            ->with(['flyer.retailer', 'page', 'retailer', 'category']);

        if ($category) {
            $query->whereIn('category_id', $category->subtreeIds());
        }

        if ($store) {
            $query->where('retailer_id', $store->id);
        }

        if ($q !== '') {
            $query->search($q);
        }

        if ($min !== null) {
            $query->where('price', '>=', $min);
        }

        if ($max !== null && $max > 0) {
            $query->where('price', '<=', $max);
        }

        $query = match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'saving' => $query->orderByRaw('COALESCE(old_price - price, 0) DESC'),
            'newest' => $query->orderByDesc('offers.created_at'),
            'ending' => $query->join('flyers', 'flyers.id', '=', 'offers.flyer_id')->select('offers.*')->orderBy('flyers.valid_until'),
            default => $query->orderByDesc('verified_at')->orderByDesc('offers.id'),
        };

        $offers = $query->paginate(config('qdealme.offers_per_page'))->withQueryString();

        if ($request->boolean('partial')) {
            return view('deals.partials.grid-items', ['offers' => $offers]);
        }

        $storeCounts = Offer::query()->live()
            ->whereHas('flyer', fn (Builder $f) => $f->forCity($city))
            ->when($category, fn (Builder $o) => $o->whereIn('category_id', $category->subtreeIds()))
            ->selectRaw('retailer_id, count(*) as aggregate')
            ->groupBy('retailer_id')
            ->pluck('aggregate', 'retailer_id');

        $stores = Retailer::query()->whereIn('id', $storeCounts->keys())->orderBy('name_en')->get();

        $priceCeiling = (float) (Offer::query()->live()
            ->whereHas('flyer', fn (Builder $f) => $f->forCity($city))
            ->when($category, fn (Builder $o) => $o->whereIn('category_id', $category->subtreeIds()))
            ->max('price') ?? 0);

        $title = $category
            ? __('seo.category_title', ['category' => $category->name, 'city' => $city->name])
            : __('seo.deals_title', ['city' => $city->name]);

        $seo = $this->seo()
            ->title($title)
            ->description(__('seo.deals_description', [
                'category' => $category?->name ?? __('nav.deals'),
                'city' => $city->name,
                'count' => fmt_number($offers->total()),
            ]))
            ->canonical($category ? route('deals.category', $category) : route('deals.index'))
            ->breadcrumbs(array_filter([
                __('nav.offers') => route('offers.index'),
                __('nav.deals') => route('deals.index'),
                $category?->name => $category ? route('deals.category', $category) : null,
            ]));

        if ($request->query() !== [] && $offers->total() === 0) {
            $seo->noindex();
        }

        return view('deals.index', [
            'seo' => $seo,
            'city' => $city,
            'category' => $category,
            'categories' => Category::query()->roots()->with('children')->get(),
            'offers' => $offers,
            'sort' => $sort,
            'store' => $store,
            'stores' => $stores,
            'storeCounts' => $storeCounts,
            'q' => $q,
            'min' => $min,
            'max' => $max,
            'priceCeiling' => $priceCeiling,
            'title' => $title,
        ]);
    }
}
