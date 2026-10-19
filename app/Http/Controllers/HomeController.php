<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('offers.index', $this->target($request, $this->detectLocale($request)));
    }

    public function locale(Request $request, string $locale): RedirectResponse
    {
        return redirect()->route('offers.index', $this->target($request, $locale));
    }

    /**
     * @return array{locale: string, country: string, city: string}
     */
    private function target(Request $request, string $locale): array
    {
        $citySlug = $request->cookie('city');

        $city = $citySlug
            ? City::query()->active()->where('slug', $citySlug)->with('country')->first()
            : null;

        $city ??= City::query()->active()->where('slug', config('qdealme.default_city'))->with('country')->first();
        $city ??= City::query()->active()->with('country')->orderBy('sort')->firstOrFail();

        return ['locale' => $locale, 'country' => $city->country->slug, 'city' => $city->slug];
    }

    private function detectLocale(Request $request): string
    {
        $supported = array_keys(config('qdealme.locales'));

        $cookie = $request->cookie('locale');

        if (in_array($cookie, $supported, true)) {
            return $cookie;
        }

        $preferred = $request->getPreferredLanguage($supported);

        return $preferred ?: config('qdealme.default_locale');
    }
}
