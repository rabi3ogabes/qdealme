<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\City;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->mixedCase()->numbers()
            : Password::min(8));

        View::composer(['components.city-picker', 'components.app-header'], function (\Illuminate\View\View $view): void {
            $country = current_country();

            $view->with('navCities', $country ? $country->cities()->active()->get() : City::query()->active()->get());
            $view->with('navCategories', Category::query()->roots()->get());
        });

        Date::use(CarbonImmutable::class);
    }
}
