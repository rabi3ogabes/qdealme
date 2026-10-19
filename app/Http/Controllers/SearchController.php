<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Flyer;
use App\Models\Offer;
use App\Models\Retailer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 120);
        $city = current_city();

        $retailers = collect();
        $flyers = collect();
        $offers = collect();

        if (mb_strlen($q) >= 2) {
            $retailers = Retailer::query()->active()->search($q)->orderBy('name_en')->limit(12)->get();

            $flyers = Flyer::query()->published()->forCity($city)->search($q)
                ->with(['retailer', 'cover'])->latest()->limit(24)->get();

            $offers = Offer::query()->live()
                ->whereHas('flyer', fn (Builder $f) => $f->forCity($city))
                ->search($q)
                ->with(['flyer.retailer', 'page', 'retailer', 'category'])
                ->orderBy('price')
                ->limit(48)
                ->get();
        }

        $seo = $this->seo()
            ->title($q !== '' ? __('seo.search_title', ['query' => $q]) : __('nav.search'))
            ->description(__('seo.search_description', ['city' => $city->name]))
            ->noindex();

        return view('search.index', [
            'seo' => $seo,
            'q' => $q,
            'retailers' => $retailers,
            'flyers' => $flyers,
            'offers' => $offers,
            'trending' => collect(config('trending', [])),
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 120);
        $city = current_city();

        if (mb_strlen($q) < 2) {
            return response()->json(['retailers' => [], 'flyers' => [], 'offers' => [], 'categories' => []]);
        }

        $retailers = Retailer::query()->active()->search($q)->limit(5)->get()
            ->map(fn (Retailer $r) => [
                'title' => $r->name,
                'subtitle' => $r->type->label(),
                'url' => route('stores.show', $r),
                'image' => $r->logoUrl(),
            ]);

        $flyers = Flyer::query()->published()->forCity($city)->search($q)->with(['retailer', 'cover'])->latest()->limit(5)->get()
            ->map(fn (Flyer $f) => [
                'title' => $f->title,
                'subtitle' => $f->retailer->name.' · '.$f->countdown(local_today($city))->label(),
                'url' => $f->url(),
                'image' => $f->coverPage()?->url(320),
            ]);

        $offers = Offer::query()->live()
            ->whereHas('flyer', fn (Builder $f) => $f->forCity($city))
            ->search($q)->with(['retailer'])->orderBy('price')->limit(6)->get()
            ->map(fn (Offer $o) => [
                'title' => $o->title,
                'subtitle' => $o->retailer->name.' · '.fmt_price($o->price),
                'url' => route('deals.show', ['offer' => $o->routeSegment()]),
                'image' => null,
            ]);

        $categories = Category::query()->search($q)->limit(4)->get()
            ->map(fn (Category $c) => [
                'title' => $c->name,
                'subtitle' => __('nav.deals'),
                'url' => route('deals.category', $c),
                'image' => null,
            ]);

        return response()->json([
            'retailers' => $retailers,
            'flyers' => $flyers,
            'offers' => $offers,
            'categories' => $categories,
            'more' => route('search.index', ['q' => $q]),
        ]);
    }
}
