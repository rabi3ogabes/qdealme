<?php

use App\Models\City;
use App\Models\Country;
use App\Support\Arabic;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Route;

if (! function_exists('current_city')) {
    /**
     * The city resolved by the locale middleware for this request.
     */
    function current_city(): ?City
    {
        return app()->bound('qdealme.city') ? app('qdealme.city') : null;
    }
}

if (! function_exists('current_country')) {
    function current_country(): ?Country
    {
        return app()->bound('qdealme.country') ? app('qdealme.country') : null;
    }
}

if (! function_exists('locale_dir')) {
    /**
     * Text direction for the given (or current) locale.
     */
    function locale_dir(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return config("qdealme.locales.{$locale}.dir", 'ltr');
    }
}

if (! function_exists('is_rtl')) {
    function is_rtl(): bool
    {
        return locale_dir() === 'rtl';
    }
}

if (! function_exists('other_locale')) {
    function other_locale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar' ? 'en' : 'ar';
    }
}

if (! function_exists('localized_url')) {
    /**
     * The current page's URL in another locale (keeps query string).
     */
    function localized_url(string $locale): string
    {
        $route = Route::current();

        if ($route === null || ! $route->hasParameter('locale')) {
            return route('home.redirect', ['locale' => $locale]);
        }

        $params = array_merge($route->parameters(), ['locale' => $locale]);

        $url = route($route->getName() ?? 'offers.index', $params);
        $query = request()->getQueryString();

        return $query ? $url.'?'.$query : $url;
    }
}

if (! function_exists('city_url')) {
    /**
     * The current page in another city. Pages scoped to a specific record
     * (store, flyer) fall back to the city home.
     */
    function city_url(City $city): string
    {
        $route = Route::current();
        $cityOnly = ['offers.index', 'stores.index', 'deals.index', 'deals.category', 'search.index'];

        if ($route === null || ! in_array($route->getName(), $cityOnly, true)) {
            return route('offers.index', ['country' => $city->country->slug, 'city' => $city->slug]);
        }

        $params = array_merge($route->parameters(), ['country' => $city->country->slug, 'city' => $city->slug]);

        return route($route->getName(), $params);
    }
}

if (! function_exists('digit_style')) {
    /**
     * Whether numbers should be shown with Eastern Arabic digits.
     */
    function digit_style(): string
    {
        if (auth()->check() && auth()->user()->digits) {
            return auth()->user()->digits;
        }

        $cookie = request()->cookie('digits');

        return in_array($cookie, ['latin', 'arabic'], true) ? $cookie : 'latin';
    }
}

if (! function_exists('fmt_number')) {
    function fmt_number(int|float|string $number, int $decimals = 0): string
    {
        $formatted = number_format((float) $number, $decimals, '.', ',');

        return app()->getLocale() === 'ar' && digit_style() === 'arabic'
            ? Arabic::toArabicDigits($formatted)
            : $formatted;
    }
}

if (! function_exists('fmt_price')) {
    /**
     * Format a price in the site currency. Whole amounts drop the decimals so
     * "QAR 25" reads cleaner than "QAR 25.00" on a card.
     */
    function fmt_price(int|float|string|null $amount, bool $withCurrency = true, ?int $decimals = null): string
    {
        if ($amount === null) {
            return '';
        }

        $amount = (float) $amount;
        $decimals ??= floor($amount) === $amount ? 0 : 2;
        $number = fmt_number($amount, $decimals);

        if (! $withCurrency) {
            return $number;
        }

        $currency = __('currency.'.config('qdealme.currency'));

        return app()->getLocale() === 'ar' ? $number.' '.$currency : $currency.' '.$number;
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(CarbonInterface $date, string $format = 'j F'): string
    {
        $text = $date->copy()->locale(app()->getLocale())->translatedFormat($format);

        return app()->getLocale() === 'ar' && digit_style() === 'arabic' ? Arabic::toArabicDigits($text) : $text;
    }
}

if (! function_exists('local_today')) {
    function local_today(?City $city = null): CarbonInterface
    {
        $tz = $city?->timezone() ?? config('qdealme.timezone');

        return today($tz);
    }
}

if (! function_exists('brand')) {
    function brand(string $key = 'name'): string
    {
        return (string) config('qdealme.brand.'.$key);
    }
}
