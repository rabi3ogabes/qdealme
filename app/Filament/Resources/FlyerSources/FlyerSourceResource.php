<?php

namespace App\Filament\Resources\FlyerSources;

use App\Actions\Flyers\PullAllFeeds;
use App\Actions\Flyers\PullLatestFlyer;
use App\Actions\Flyers\PullSocialPosts;
use App\Actions\Flyers\RevokeFlyerSource;
use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use App\Enums\UserRole;
use App\Exceptions\FeedPullException;
use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\Flyers\FlyerResource;
use App\Filament\Resources\FlyerSources\Pages\ManageFlyerSources;
use App\Models\FlyerSource;
use App\Services\SocialPostsClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
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
            TextInput::make('page_url')->label(admin_field('page_url'))->url()->maxLength(2048)->columnSpanFull()
                ->placeholder('https://www.facebook.com/…')
                ->helperText(fn (): string => __(SocialPostsClient::isConfigured() ? 'admin.import.page_help_auto' : 'admin.import.page_help'))
                ->visible(fn (Get $get) => in_array($get('type'), [SourceType::SocialMedia, SourceType::SocialMedia->value], true)),
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
                TextColumn::make('last_uploaded_at')->label(admin_field('last_uploaded_at'))->since()->sortable()
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
                static::uploadLinkAction(),
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
     * The private link where the store's own team sends its flyer: copy it,
     * send it on WhatsApp or by email, or replace it with a new one.
     */
    public static function uploadLinkAction(): Action
    {
        return Action::make('uploadLink')
            ->label(__('admin.upload_link.action'))
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->visible(fn (FlyerSource $record): bool => static::canManageLicences() && $record->isApproved())
            ->modalIcon(Heroicon::OutlinedLink)
            ->modalHeading(fn (FlyerSource $record): string => __('admin.upload_link.heading', ['store' => (string) $record->retailer?->name]))
            ->modalDescription(__('admin.upload_link.description'))
            ->schema(fn (FlyerSource $record): array => [
                TextEntry::make('link')
                    ->label(admin_field('upload_link'))
                    ->state($record->uploadUrl())
                    ->copyable()
                    ->copyMessage(__('admin.upload_link.copied'))
                    ->extraAttributes(['dir' => 'ltr']),
                TextEntry::make('last_upload')
                    ->label(admin_field('last_uploaded_at'))
                    ->state($record->last_uploaded_at?->diffForHumans() ?? __('admin.upload_link.never')),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.upload_link.close'))
            ->extraModalFooterActions(fn (FlyerSource $record): array => [
                Action::make('whatsapp')
                    ->label(__('admin.upload_link.whatsapp'))
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('success')
                    ->url('https://wa.me/?text='.rawurlencode(static::uploadLinkMessage($record)), shouldOpenInNewTab: true),
                Action::make('email')
                    ->label(__('admin.upload_link.email'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->url(static::uploadLinkMailto($record), shouldOpenInNewTab: true),
                Action::make('rotate')
                    ->label(__('admin.upload_link.rotate'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.upload_link.rotate_confirm'))
                    ->action(function () use ($record): void {
                        $record->rotateUploadToken();
                        Notification::make()->success()->title(__('admin.upload_link.rotated'))->send();
                    }),
            ]);
    }

    /**
     * A ready-to-send bilingual message with the store's upload link.
     */
    public static function uploadLinkMessage(FlyerSource $source): string
    {
        $url = $source->uploadUrl();
        $retailer = $source->retailer;

        return __('admin.upload_link.message', ['store' => (string) $retailer?->name_ar, 'url' => $url], 'ar')
            ."\n\n"
            .__('admin.upload_link.message', ['store' => (string) $retailer?->name_en, 'url' => $url], 'en');
    }

    private static function uploadLinkMailto(FlyerSource $source): string
    {
        $contact = (string) $source->permission_contact;
        $to = filter_var($contact, FILTER_VALIDATE_EMAIL) ? $contact : '';

        return 'mailto:'.rawurlencode($to)
            .'?subject='.rawurlencode(__('admin.upload_link.email_subject', ['store' => (string) $source->retailer?->name_en], 'en'))
            .'&body='.rawurlencode(static::uploadLinkMessage($source));
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
        return PullAllFeeds::isPullable($source);
    }

    /**
     * Shared by the source and store tables: pull, then open the new draft.
     */
    public static function pull(FlyerSource $source, Component $livewire): void
    {
        $isSocial = $source->type === SourceType::SocialMedia;

        try {
            $flyers = $isSocial
                ? app(PullSocialPosts::class)->handle($source)
                : array_filter([app(PullLatestFlyer::class)->handle($source)]);
        } catch (FeedPullException $e) {
            Notification::make()->danger()->title(__('admin.pull_failed'))->body($e->getMessage())->persistent()->send();

            return;
        }

        if ($flyers === []) {
            Notification::make()->info()->title(__($isSocial ? 'admin.pull_no_new_posts' : 'admin.pull_unchanged'))->send();

            return;
        }

        if (count($flyers) === 1) {
            Notification::make()->success()->title(__('admin.pull_created'))->send();
            $livewire->redirect(FlyerResource::getUrl('edit', ['record' => reset($flyers)]));

            return;
        }

        Notification::make()->success()->title(__('admin.pull_created_many', ['count' => count($flyers)]))->send();
        $livewire->redirect(FlyerResource::getUrl('index'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageFlyerSources::route('/')];
    }
}
