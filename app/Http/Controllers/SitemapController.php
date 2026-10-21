<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use App\Models\Flyer;
use App\Models\Retailer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $cities = City::query()->active()->with('country')->get();
        $entries = [];

        foreach ($cities as $city) {
            foreach (array_keys(config('qdealme.locales')) as $locale) {
                foreach (['pages', 'stores', 'flyers', 'deals'] as $type) {
                    $entries[] = route('sitemap.show', ['type' => $type, 'locale' => $locale, 'city' => $city->slug]);
                }
            }
        }

        return response()->view('sitemaps.index', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function show(string $type, string $locale, string $city): Response
    {
        $cityModel = City::query()->active()->where('slug', $city)->with('country')->firstOrFail();

        App::setLocale($locale);
        URL::defaults(['locale' => $locale, 'country' => $cityModel->country->slug, 'city' => $cityModel->slug]);

        $urls = match ($type) {
            'pages' => $this->pages(),
            'stores' => $this->stores($cityModel),
            'flyers' => $this->flyers($cityModel),
            'deals' => $this->deals(),
            default => abort(404),
        };

        return response()->view('sitemaps.urlset', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function pages(): array
    {
        return collect(['offers.index', 'stores.index', 'deals.index', 'pages.about', 'pages.contact', 'pages.list-store', 'pages.privacy', 'pages.terms'])
            ->map(fn ($name) => ['loc' => route($name), 'changefreq' => $name === 'offers.index' ? 'hourly' : 'monthly', 'priority' => $name === 'offers.index' ? '1.0' : '0.5'])
            ->all();
    }

    private function stores(City $city): array
    {
        return Retailer::query()->active()->get()
            ->map(fn (Retailer $r) => ['loc' => route('stores.show', $r), 'changefreq' => 'daily', 'priority' => '0.8'])
            ->all();
    }

    private function flyers(City $city): array
    {
        return Flyer::query()->published()->forCity($city)->with('retailer')->get()
            ->map(fn (Flyer $f) => ['loc' => $f->url(), 'lastmod' => $f->updated_at?->toAtomString(), 'changefreq' => 'daily', 'priority' => '0.9'])
            ->all();
    }

    private function deals(): array
    {
        return Category::query()->get()
            ->map(fn (Category $c) => ['loc' => route('deals.category', $c), 'changefreq' => 'daily', 'priority' => '0.7'])
            ->all();
    }
}
