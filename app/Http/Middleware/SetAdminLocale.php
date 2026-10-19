<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel follows the signed-in staff member's language.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (is_string($locale) && array_key_exists($locale, config('qdealme.locales'))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
