<?php

namespace App\Filament\Widgets;

use App\Enums\FlyerStatus;
use App\Filament\Resources\Flyers\FlyerResource;
use App\Models\Flyer;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Flyers that still need a person: waiting for review, failed processing,
 * or saved as drafts. Newest activity first.
 */
class ReviewQueue extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dashboard.review_queue'))
            ->description(__('admin.dashboard.review_queue_hint'))
            ->query(fn (): Builder => Flyer::query()
                ->with(['retailer', 'cover'])
                ->whereIn('status', [FlyerStatus::InReview->value, FlyerStatus::Failed->value, FlyerStatus::Draft->value, FlyerStatus::Processing->value]))
            ->defaultSort('updated_at', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->poll('30s')
            ->recordUrl(fn (Flyer $record): string => FlyerResource::getUrl('edit', ['record' => $record]))
            ->columns([
                ImageColumn::make('cover_thumb')->label(admin_field('cover'))
                    ->disk('public')
                    ->state(fn (Flyer $record) => $record->cover ? ($record->cover->derivatives['webp'][320] ?? $record->cover->original_path) : null)
                    ->imageHeight(52)->imageWidth(40)->visibleFrom('sm'),
                TextColumn::make('title_en')->label(admin_field('title_en'))
                    ->description(fn (Flyer $record) => $record->retailer?->name_en)
                    ->limit(40),
                TextColumn::make('status')->label(admin_field('status'))->badge(),
                TextColumn::make('processing_error')->label(admin_field('processing_error'))
                    ->placeholder('—')->limit(60)->color('danger')->visibleFrom('lg'),
                TextColumn::make('updated_at')->label(admin_field('updated_at'))->since()->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('admin.dashboard.open'))
                    ->icon(Heroicon::OutlinedArrowRight)
                    ->url(fn (Flyer $record): string => FlyerResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading(__('admin.dashboard.queue_clear'))
            ->emptyStateDescription(__('admin.dashboard.queue_clear_hint'));
    }
}
