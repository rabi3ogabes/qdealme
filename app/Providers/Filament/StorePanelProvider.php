<?php

namespace App\Providers\Filament;

use App\Filament\Store\Pages\UploadFlyer;
use App\Filament\Support\Brand;
use App\Http\Middleware\SetStoreLocale;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Public pages for stores, without a staff login: each approved source has a
 * private upload link (/store/upload/{token}) where the store's own team
 * sends its flyer. Everything sent there becomes a draft for staff review.
 */
class StorePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return Brand::apply($panel)
            ->id('store')
            ->path('store')
            ->homeUrl(fn (): string => route('home'))
            ->userMenu(false)
            ->databaseNotifications(false)
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, fn (): View => view('filament.store.footer'))
            ->routes(fn () => Route::get('/upload/{token}', UploadFlyer::class)->name('upload'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Also applied to Livewire updates, so the page keeps its language.
            ->middleware([SetStoreLocale::class], isPersistent: true);
    }
}
