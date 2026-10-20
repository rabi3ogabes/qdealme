<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel follows the signed-in staff member's language. Numbers
 * always use Latin digits so they match dates, prices and IDs in the tables.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (is_string($locale) && array_key_exists($locale, config('qdealme.locales'))) {
            App::setLocale($locale);
        }

        $previous = Number::defaultLocale();
        Number::useLocale(App::getLocale().'-u-nu-latn');

        try {
            return $next($request);
        } finally {
            Number::useLocale($previous);
        }
    }
}
