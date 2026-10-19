<?php

namespace App\Filament\Resources\Reports;

use App\Enums\ReportReason;
use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\Reports\Pages\ManageReports;
use App\Models\Report;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportResource extends Resource
{
    use AdminNavigation;

    protected static ?string $model = Report::class;

    protected static string $groupKey = 'inbox';

    protected static string $labelKey = 'report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    public static function statuses(): array
    {
        return collect(['open', 'resolved', 'dismissed'])->mapWithKeys(fn ($s) => [$s => __('admin.inbox_status.'.$s)])->all();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Report::query()->where('status', 'open')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['flyer.retailer', 'user']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('flyer.title_en')->label(admin_field('flyer'))->description(fn (Report $r) => $r->flyer?->retailer?->name_en)->searchable(),
                TextColumn::make('reason')->label(admin_field('reason'))->badge(),
                TextColumn::make('details')->label(admin_field('details'))->limit(60)->wrap(),
                TextColumn::make('user.email')->label(admin_field('email'))->placeholder(__('ui.guest')),
                SelectColumn::make('status')->label(admin_field('status'))->options(self::statuses())->selectablePlaceholder(false),
                TextColumn::make('created_at')->label(admin_field('created_at'))->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(admin_field('status'))->options(self::statuses())->default('open'),
                SelectFilter::make('reason')->label(admin_field('reason'))->options(ReportReason::class),
            ])
            ->recordActions([
                Action::make('flyer')->label(__('admin.models.flyer.one'))->icon(Heroicon::OutlinedNewspaper)
                    ->url(fn (Report $r) => $r->flyer ? route('filament.admin.resources.flyers.edit', $r->flyer) : null),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReports::route('/')];
    }
}
