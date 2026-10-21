<?php

namespace App\Filament\Widgets;

use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Enums\Verification;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Flyers\FlyerResource;
use App\Filament\Resources\FlyerSources\FlyerSourceResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\ContactMessage;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Models\Lead;
use App\Models\Offer;
use App\Models\Report;
use Filament\Widgets\Widget;

/**
 * Everything waiting on staff, most urgent first, each linking straight to
 * the filtered list. Items with nothing waiting drop out; when the list is
 * empty the panel says so.
 */
class AttentionPanel extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.attention-panel';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected function getViewData(): array
    {
        return ['items' => $this->items()];
    }

    /**
     * @return array<int, array{label: string, count: int, url: string, tone: string, icon: string}>
     */
    protected function items(): array
    {
        $today = today(config('qdealme.timezone'))->toDateString();

        $items = [
            [__('admin.failed_processing'), Flyer::query()->where('status', FlyerStatus::Failed->value)->count(), FlyerResource::getUrl('index', ['tab' => FlyerStatus::Failed->value]), 'danger', 'heroicon-o-exclamation-triangle'],
            [__('admin.flyer_in_review'), Flyer::query()->where('status', FlyerStatus::InReview->value)->count(), FlyerResource::getUrl('index', ['tab' => FlyerStatus::InReview->value]), 'warning', 'heroicon-o-eye'],
            [__('admin.expiring_today'), Flyer::query()->published()->whereDate('valid_until', $today)->count(), FlyerResource::getUrl('index', ['tab' => FlyerStatus::Published->value]), 'warning', 'heroicon-o-clock'],
            [__('admin.pending_licences'), FlyerSource::query()->where('license_status', LicenseStatus::Pending->value)->count(), FlyerSourceResource::getUrl('index', ['filters' => ['license_status' => ['value' => LicenseStatus::Pending->value]]]), 'primary', 'heroicon-o-shield-check'],
            [__('admin.unverified_offers'), Offer::query()->where('verification', Verification::Unverified->value)->count(), OfferResource::getUrl('index', ['tab' => Verification::Unverified->value]), 'gold', 'heroicon-o-tag'],
            [__('admin.open_reports'), Report::query()->where('status', 'open')->count(), ReportResource::getUrl('index'), 'danger', 'heroicon-o-flag'],
            [__('admin.new_leads'), Lead::query()->where('status', 'new')->count(), LeadResource::getUrl('index', ['filters' => ['status' => ['value' => 'new']]]), 'info', 'heroicon-o-building-office-2'],
            [__('admin.new_messages'), ContactMessage::query()->where('status', 'new')->count(), ContactMessageResource::getUrl('index', ['filters' => ['status' => ['value' => 'new']]]), 'info', 'heroicon-o-envelope'],
        ];

        return collect($items)
            ->filter(fn (array $item) => $item[1] > 0)
            ->map(fn (array $item) => array_combine(['label', 'count', 'url', 'tone', 'icon'], $item))
            ->values()
            ->all();
    }
}
