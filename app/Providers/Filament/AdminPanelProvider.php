<?php

namespace App\Providers\Filament;

use App\Enums\AdminGroup;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Filament\Widgets\AttentionPanel;
use App\Filament\Widgets\FlyerActivityChart;
use App\Filament\Widgets\KpiOverview;
use App\Filament\Widgets\ReviewQueue;
use App\Http\Middleware\SetAdminLocale;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Forms\Components\DatePicker;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Staff work in Qatar: show every date and time in Qatar time.
        FilamentTimezone::set(config('qdealme.timezone'));

        // Latin digits everywhere in the admin, matching dates, prices and IDs.
        Table::configureUsing(fn (Table $table) => $table->defaultNumberLocale(fn (): string => app()->getLocale().'-u-nu-latn'));
        Schema::configureUsing(fn (Schema $schema) => $schema->defaultNumberLocale(fn (): string => app()->getLocale().'-u-nu-latn'));

        // "17 Oct 2026" / "17 أكتوبر 2026" rather than the US "Oct 17, 2026".
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->displayFormat('j M Y'));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()->brandName('QDealMe')],
                isRequired: (bool) config('qdealme.admin_require_mfa'),
            )
            ->brandName('QDealMe')
            ->brandLogo(asset('images/logo.svg'))
            ->darkModeBrandLogo(asset('images/logo-dark.svg'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            ->font('Readex Pro', asset('fonts/admin-fonts.css'), LocalFontProvider::class)
            ->colors([
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
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('17rem')
            ->maxContentWidth(Width::ScreenTwoExtraLarge)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.admin.head'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn (): View => view('filament.admin.login-intro'))
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, fn (): View => view('filament.admin.simple-footer'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn (): View => view('filament.admin.sidebar-footer'))
            // Labels are resolved per request (not at boot), so the sidebar
            // groups follow each staff member's language like everything else.
            ->navigationGroups(collect(AdminGroup::cases())->mapWithKeys(fn (AdminGroup $group) => [
                $group->name => NavigationGroup::make()->label(fn (): string => $group->getLabel()),
            ])->all())
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->databaseNotifications(false)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                KpiOverview::class,
                AttentionPanel::class,
                FlyerActivityChart::class,
                ReviewQueue::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                SetAdminLocale::class,
            ], isPersistent: true);
    }
}
