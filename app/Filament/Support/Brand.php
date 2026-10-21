<?php

namespace App\Filament\Support;

use Filament\FontProviders\LocalFontProvider;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

/**
 * The QDealMe look shared by the staff panel and the store upload pages.
 */
class Brand
{
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->brandName('QDealMe')
            ->brandLogo(asset('images/logo.svg'))
            ->darkModeBrandLogo(asset('images/logo-dark.svg'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            ->font('Readex Pro', asset('fonts/admin-fonts.css'), LocalFontProvider::class)
            ->colors(static::colors())
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.admin.head'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function colors(): array
    {
        return [
            // The brand's aubergine ("ink") scale, matching resources/css/app.css.
            'primary' => [
                50 => '#fbf3f6', 100 => '#f6e4eb', 200 => '#edc9d7', 300 => '#dda0b7',
                400 => '#c76f91', 500 => '#ab4a70', 600 => '#8e3358', 700 => '#772746',
                800 => '#5e1f38', 900 => '#4b1a2e', 950 => '#2e0e1b',
            ],
            'gold' => [
                50 => '#fdf9ec', 100 => '#faf0cf', 200 => '#f4df9d', 300 => '#ecc866',
                400 => '#e3b03a', 500 => '#c9a227', 600 => '#a8801c', 700 => '#866218',
                800 => '#6e4e1a', 900 => '#5c411a', 950 => '#3a2810',
            ],
            'gray' => Color::Stone,
            'warning' => Color::Amber,
            'success' => Color::Emerald,
            'danger' => Color::Rose,
            'info' => Color::Sky,
        ];
    }
}
