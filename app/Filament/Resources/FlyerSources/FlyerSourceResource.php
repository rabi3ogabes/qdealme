<?php

namespace App\Filament\Resources\FlyerSources;

use App\Actions\Flyers\PullAllFeeds;
use App\Actions\Flyers\PullLatestFlyer;
use App\Actions\Flyers\RevokeFlyerSource;
use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use App\Enums\UserRole;
use App\Exceptions\FeedPullException;
use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\Flyers\FlyerResource;
use App\Filament\Resources\FlyerSources\Pages\ManageFlyerSources;
use App\Models\FlyerSource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Component;

/**
 * Permission records. A flyer can only be created from an approved source;
 * revoking a source takes all of its flyers offline.
 */
class FlyerSourceResource extends Resource
{
    use AdminNavigation;

    protected static ?string $model = FlyerSource::class;

    protected static string $groupKey = 'content';

    protected static string $labelKey = 'flyer_source';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = FlyerSource::query()->where('license_status', LicenseStatus::Pending->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function canManageLicences(): bool
    {
        return in_array(auth()->user()?->role, [UserRole::SuperAdmin, UserRole::ContentAdmin], true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('retailer_id')->label(admin_field('retailer'))->relationship('retailer', 'name_en')->searchable()->preload()->required(),
            Select::make('type')->label(admin_field('type'))->options(SourceType::class)->required()->native(false)->live(),
            TextInput::make('permission_contact')->label(admin_field('permission_contact'))->maxLength(255)
                ->helperText(__('admin.source_help')),
            FileUpload::make('license_document_path')->label(admin_field('license_document'))
                ->disk('local')->directory('licences')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)->downloadable(),
            TextInput::make('feed_url')->label(admin_field('feed_url'))->url()->maxLength(2048)->columnSpanFull()
                ->helperText(__('admin.feed_help'))
                ->visible(fn (Get $get) => static::isFeedType($get('type'))),
            Textarea::make('notes')->label(admin_field('notes'))->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('retailer.name_en')->label(admin_field('retailer'))->searchable()->sortable(),
                TextColumn::make('type')->label(admin_field('type'))->badge(),
                TextColumn::make('license_status')->label(admin_field('license_status'))->badge()->sortable(),
                TextColumn::make('permission_contact')->label(admin_field('permission_contact'))->limit(40),
                TextColumn::make('approver.name')->label(admin_field('approved_by')),
                TextColumn::make('approved_at')->label(admin_field('approved_at'))->dateTime()->sortable(),
                TextColumn::make('last_pulled_at')->label(admin_field('last_pulled_at'))->dateTime()->sortable()
                    ->description(fn (FlyerSource $record) => $record->last_pull_error)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('license_status')->label(admin_field('license_status'))->options(LicenseStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('admin.approve_source'))
                    ->icon(Heroicon::OutlinedCheckBadge)->color('success')
                    ->visible(fn (FlyerSource $s) => static::canManageLicences() && $s->license_status !== LicenseStatus::Approved)
                    ->requiresConfirmation()
                    ->action(function (FlyerSource $s): void {
                        $s->forceFill([
                            'license_status' => LicenseStatus::Approved,
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ])->save();
                        Notification::make()->success()->title(__('admin.approve_source'))->send();
                    }),
                Action::make('revoke')
                    ->label(__('admin.revoke_source'))
                    ->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (FlyerSource $s) => static::canManageLicences() && $s->license_status === LicenseStatus::Approved)
                    ->requiresConfirmation()
                    ->schema([Textarea::make('reason')->label(admin_field('notes'))->required()])
                    ->action(function (FlyerSource $s, array $data, RevokeFlyerSource $revoke): void {
                        $count = $revoke->handle($s, $data['reason']);
                        Notification::make()->warning()->title(__('admin.revoke_source'))->body((string) $count)->send();
                    }),
                Action::make('pull')
                    ->label(__('admin.pull_latest'))
                    ->icon(Heroicon::OutlinedArrowDownTray)->color('primary')
                    ->visible(fn (FlyerSource $record) => static::canManageLicences() && static::canPull($record))
                    ->requiresConfirmation()->modalDescription(__('admin.pull_confirm'))
                    ->action(fn (FlyerSource $record, Component $livewire) => static::pull($record, $livewire)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('pullSelected')
                        ->label(__('admin.pull_selected'))
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->visible(fn () => static::canManageLicences())
                        ->requiresConfirmation()->modalDescription(__('admin.pull_bulk_confirm'))
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => static::pullMany($records)),
                ]),
            ]);
    }

    /**
     * Pull several feeds (or all, when $sources is null) and report the outcome.
     *
     * @param  Collection<int, FlyerSource>|null  $sources
     */
    public static function pullMany(?Collection $sources = null): void
    {
        $summary = app(PullAllFeeds::class)->handle($sources);
        $total = $summary['created'] + $summary['unchanged'] + count($summary['failed']);

        if ($total === 0) {
            Notification::make()->warning()->title(__('admin.pull_none'))->body(__('admin.pull_none_hint'))->persistent()->send();

            return;
        }

        $lines = [__('admin.pull_summary', [
            'created' => $summary['created'],
            'unchanged' => $summary['unchanged'],
            'failed' => count($summary['failed']),
        ])];

        foreach ($summary['failed'] as $store => $reason) {
            $lines[] = "• {$store}: {$reason}";
        }

        Notification::make()
            ->title(__('admin.pull_done'))
            ->body(new HtmlString(implode('<br>', array_map('e', $lines))))
            ->status($summary['failed'] === [] ? 'success' : ($summary['created'] > 0 ? 'warning' : 'danger'))
            ->persistent($summary['failed'] !== [])
            ->send();
    }

    public static function isFeedType(mixed $type): bool
    {
        return $type === SourceType::RetailerFeed || $type === SourceType::RetailerFeed->value;
    }

    public static function canPull(FlyerSource $source): bool
    {
        return $source->type === SourceType::RetailerFeed && $source->isApproved() && filled($source->feed_url);
    }

    /**
     * Shared by the source and store tables: pull, then open the new draft.
     */
    public static function pull(FlyerSource $source, Component $livewire): void
    {
        try {
            $flyer = app(PullLatestFlyer::class)->handle($source);
        } catch (FeedPullException $e) {
            Notification::make()->danger()->title(__('admin.pull_failed'))->body($e->getMessage())->persistent()->send();

            return;
        }

        if ($flyer === null) {
            Notification::make()->info()->title(__('admin.pull_unchanged'))->send();

            return;
        }

        Notification::make()->success()->title(__('admin.pull_created'))->send();
        $livewire->redirect(FlyerResource::getUrl('edit', ['record' => $flyer]));
    }

    public static function getPages(): array
    {
        return ['index' => ManageFlyerSources::route('/')];
    }
}
