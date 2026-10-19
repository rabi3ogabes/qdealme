<?php

namespace App\Http\Middleware;

use App\Models\City;
use App\Models\Country;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the {locale}/{country}/{city} prefix on every public route, sets
 * the application locale and text direction, remembers the shopper's choice
 * in cookies and shares the resolved models with all views.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale');

        if (! array_key_exists($locale, config('qdealme.locales'))) {
            abort(404);
        }

        $country = Country::query()->where('slug', $request->route('country'))->where('is_active', true)->first();
        $city = $country
            ? City::query()->where('country_id', $country->id)->where('slug', $request->route('city'))->where('is_active', true)->first()
            : null;

        if ($country === null || $city === null) {
            abort(404);
        }

        $city->setRelation('country', $country);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        app()->instance('qdealme.country', $country);
        app()->instance('qdealme.city', $city);

        URL::defaults(['locale' => $locale, 'country' => $country->slug, 'city' => $city->slug]);

        // Keep the models out of the route parameters passed to controllers so
        // controller signatures stay focused on their own parameters.
        $request->route()->forgetParameter('locale');
        $request->route()->forgetParameter('country');
        $request->route()->forgetParameter('city');

        View::share([
            'currentLocale' => $locale,
            'currentCountry' => $country,
            'currentCity' => $city,
            'dir' => locale_dir($locale),
        ]);

        $response = $next($request);

        $minutes = 60 * 24 * 365;

        if ($request->cookie('locale') !== $locale) {
            Cookie::queue(Cookie::make('locale', $locale, $minutes, '/', null, null, false, false, 'lax'));
        }

        if ($request->cookie('city') !== $city->slug) {
            Cookie::queue(Cookie::make('city', $city->slug, $minutes, '/', null, null, false, false, 'lax'));
        }

        return $response;
    }
}
