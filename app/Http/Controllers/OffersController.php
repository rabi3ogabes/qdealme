<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Retailer;
use App\Services\FlyerListing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OffersController extends Controller
{
    public function index(Request $request): View
    {
        $city = current_city();
        $listing = FlyerListing::fromRequest($request, $city);
        $flyers = $listing->paginate();

        if ($request->boolean('partial')) {
            return view('offers.partials.grid-items', ['flyers' => $flyers]);
        }

        $store = $listing->store ? Retailer::query()->where('slug', $listing->store)->first() : null;

        $title = $store
            ? __('seo.store_offers_title', ['store' => $store->name, 'city' => $city->name])
            : __('seo.city_title', ['city' => $city->name]);

        $description = __('seo.city_description', [
            'city' => $city->name,
            'count' => fmt_number($flyers->total()),
            'brand' => brand(),
        ]);

        $seo = $this->seo()
            ->title($title)
            ->description($description)
            ->canonical(route('offers.index', $listing->filters()))
            ->jsonLd($this->organizationSchema())
            ->jsonLd([
                '@type' => 'WebSite',
                'name' => brand(),
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => route('search.index').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ])
            ->jsonLd([
                '@type' => 'ItemList',
                'name' => $title,
                'numberOfItems' => $flyers->total(),
                'itemListElement' => $flyers->getCollection()->values()->map(fn ($flyer, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $flyer->retailer->name.' – '.$flyer->title,
                    'url' => $flyer->url(),
                ])->all(),
            ]);

        if ($listing->filters() !== [] && $flyers->total() === 0) {
            $seo->noindex();
        }

        return view('offers.index', [
            'seo' => $seo,
            'city' => $city,
            'flyers' => $flyers,
            'listing' => $listing,
            'retailers' => $listing->retailersStrip(),
            'typeCounts' => $listing->typeCounts(),
            'categories' => Category::query()->roots()->with('children')->get(),
            'store' => $store,
            'cities' => $city->country->cities()->active()->get(),
        ]);
    }
}
