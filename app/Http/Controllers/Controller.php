<?php

namespace App\Http\Controllers;

use App\Support\Seo;

abstract class Controller
{
    protected function seo(): Seo
    {
        return Seo::make()->alternatesForCurrentRoute();
    }

    protected function cityName(): string
    {
        return current_city()?->name ?? '';
    }

    protected function organizationSchema(): array
    {
        return [
            '@type' => 'Organization',
            'name' => brand(),
            'url' => url('/'),
            'logo' => asset('images/logo.svg'),
            'sameAs' => array_values(array_filter(config('qdealme.brand.social', []))),
        ];
    }
}
