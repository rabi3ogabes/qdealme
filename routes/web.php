<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DealsController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\FlyersController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OffersController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StoresController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Entry points & redirects
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/{locale}', [HomeController::class, 'locale'])
    ->where('locale', 'ar|en')
    ->name('home.redirect');

Route::post('/preferences/digits', [PreferencesController::class, 'digits'])->name('preferences.digits');

/*
|--------------------------------------------------------------------------
| Sitemaps
|--------------------------------------------------------------------------
*/

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemaps/{type}-{locale}-{city}.xml', [SitemapController::class, 'show'])
    ->where(['type' => 'pages|stores|flyers|deals', 'locale' => 'ar|en'])
    ->name('sitemap.show');

/*
|--------------------------------------------------------------------------
| Localised public site: /{locale}/{country}/{city}/...
|--------------------------------------------------------------------------
*/

Route::prefix('{locale}/{country}/{city}')
    ->where(['locale' => 'ar|en'])
    ->middleware('locale')
    ->group(function (): void {
        Route::get('/', [OffersController::class, 'index'])->name('offers.index');

        Route::get('/stores', [StoresController::class, 'index'])->name('stores.index');
        Route::get('/stores/{retailer}', [StoresController::class, 'show'])->name('stores.show');
        Route::get('/stores/{retailer}/archive', [StoresController::class, 'archive'])->name('stores.archive');

        Route::get('/flyers/{retailer}/{flyer}', [FlyersController::class, 'show'])->name('flyers.show');

        Route::get('/deals', [DealsController::class, 'index'])->name('deals.index');
        Route::get('/deals/{category}', [DealsController::class, 'category'])->name('deals.category');
        Route::get('/p/{offer}', [DealsController::class, 'show'])->name('deals.show');

        Route::get('/search', [SearchController::class, 'index'])->name('search.index');
        Route::get('/search/suggest', [SearchController::class, 'suggest'])
            ->middleware('throttle:120,1')
            ->name('search.suggest');

        Route::get('/about', [PagesController::class, 'about'])->name('pages.about');
        Route::get('/privacy', [PagesController::class, 'privacy'])->name('pages.privacy');
        Route::get('/terms', [PagesController::class, 'terms'])->name('pages.terms');
        Route::get('/contact', [PagesController::class, 'contact'])->name('pages.contact');
        Route::post('/contact', [PagesController::class, 'sendContact'])
            ->middleware('throttle:5,10')
            ->name('pages.contact.send');
        Route::get('/list-your-store', [PagesController::class, 'listStore'])->name('pages.list-store');
        Route::post('/list-your-store', [PagesController::class, 'storeLead'])
            ->middleware('throttle:5,10')
            ->name('pages.list-store.send');

        Route::post('/report/{flyer}', [EngagementController::class, 'report'])
            ->middleware('throttle:10,10')
            ->name('flyers.report');

        Route::middleware('guest')->group(function (): void {
            Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
            Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
            Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
            Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.attempt');
        });

        Route::middleware('auth')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/account', [AccountController::class, 'show'])->name('account');
            Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
            Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
            Route::get('/saved', [AccountController::class, 'saved'])->name('account.saved');

            Route::post('/follow/{retailer}', [EngagementController::class, 'toggleFollow'])->name('retailers.follow');
            Route::post('/favorite/{flyer}', [EngagementController::class, 'toggleFavorite'])->name('flyers.favorite');
        });
    });
