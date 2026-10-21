<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

/**
 * The store upload pages open in Arabic; `?lang=en` switches them to English
 * and the choice is remembered for the visit. Numbers use Latin digits, as in
 * the staff panel.
 */
class SetStoreLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = config('qdealme.locales');
        $requested = $request->query('lang');

        if (is_string($requested) && array_key_exists($requested, $locales)) {
            $request->session()->put('store_locale', $requested);
        }

        $locale = $request->session()->get('store_locale');
        App::setLocale(is_string($locale) && array_key_exists($locale, $locales) ? $locale : config('qdealme.default_locale'));

        $previous = Number::defaultLocale();
        Number::useLocale(App::getLocale().'-u-nu-latn');

        try {
            return $next($request);
        } finally {
            Number::useLocale($previous);
        }
    }
}
