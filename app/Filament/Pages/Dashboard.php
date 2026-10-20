<?php

namespace App\Filament\Pages;

use App\Actions\Flyers\PullAllFeeds;
use App\Filament\Resources\Flyers\FlyerResource;
use App\Filament\Resources\FlyerSources\FlyerSourceResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * The staff home: a greeting in Qatar time, the key numbers, what needs
 * attention now, recent activity and the review queue.
 */
class Dashboard extends BaseDashboard
{
    public function getPageClasses(): array
    {
        return ['qd-dashboard'];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 3];
    }

    public function getHeading(): string
    {
        $hour = (int) now(config('qdealme.timezone'))->format('G');
        $greeting = match (true) {
            $hour < 12 => __('admin.dashboard.morning'),
            $hour < 18 => __('admin.dashboard.afternoon'),
            default => __('admin.dashboard.evening'),
        };

        $name = trim((string) strtok((string) auth()->user()?->name, ' '));

        return $name === '' ? $greeting : __('admin.dashboard.greeting', ['greeting' => $greeting, 'name' => $name]);
    }

    public function getSubheading(): string
    {
        return now(config('qdealme.timezone'))->translatedFormat('l j F Y').' · '.__('admin.dashboard.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pullFeeds')
                ->label(__('admin.pull_all'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => FlyerSourceResource::canManageLicences() && PullAllFeeds::pullable()->exists())
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedArrowDownTray)
                ->modalDescription(fn (): string => __('admin.pull_all_confirm', ['count' => PullAllFeeds::pullable()->count()]))
                ->action(fn () => FlyerSourceResource::pullMany()),
            Action::make('reviewQueue')
                ->label(__('admin.dashboard.open_review_queue'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(FlyerResource::getUrl('index', ['tab' => 'in_review'])),
            Action::make('newFlyer')
                ->label(__('admin.dashboard.new_flyer'))
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => FlyerResource::canCreate())
                ->url(FlyerResource::getUrl('create')),
        ];
    }
}
