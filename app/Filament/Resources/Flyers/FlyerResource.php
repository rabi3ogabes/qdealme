<?php

namespace App\Filament\Resources\Flyers;

use App\Actions\Flyers\ImportFlyerFromPost;
use App\Actions\Flyers\PublishFlyer;
use App\Actions\Flyers\PullAllFeeds;
use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use App\Exceptions\PostImportException;
use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\Flyers\Pages\CreateFlyer;
use App\Filament\Resources\Flyers\Pages\EditFlyer;
use App\Filament\Resources\Flyers\Pages\ListFlyers;
use App\Filament\Resources\Flyers\RelationManagers\OffersRelationManager;
use App\Filament\Resources\FlyerSources\FlyerSourceResource;
use App\Jobs\ProcessFlyerUpload;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Models\Retailer;
use App\Services\FlyerImageProcessor;
use App\Support\PostDates;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Livewire\Component;

class FlyerResource extends Resource
{
    use AdminNavigation;

    protected static ?string $model = Flyer::class;

    protected static string $groupKey = 'content';

    protected static string $labelKey = 'flyer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'title_en';

    public static function getNavigationBadge(): ?string
    {
        $count = Flyer::query()->where('status', FlyerStatus::InReview->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(admin_field('general'))->columns(2)->schema([
                Select::make('retailer_id')->label(admin_field('retailer'))
                    ->relationship('retailer', 'name_en')->searchable()->preload()->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('flyer_source_id', null)),
                Select::make('flyer_source_id')->label(admin_field('source'))
                    ->helperText(__('admin.source_help'))
                    ->options(fn (Get $get) => static::approvedSourceOptions($get('retailer_id')))
                    ->rule(fn (Get $get) => static::approvedSourceRule($get('retailer_id')))
                    ->required()->native(false),
                TextInput::make('title_ar')->label(admin_field('title_ar'))->required()->maxLength(160)->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('title_en')->label(admin_field('title_en'))->required()->maxLength(160)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set, ?Flyer $record): void {
                        if ($record === null) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')->label(admin_field('slug'))->required()->alphaDash()->maxLength(160),
                Placeholder::make('status_display')->label(admin_field('status'))
                    ->content(fn (?Flyer $record) => $record?->status->label() ?? FlyerStatus::Draft->label()),
            ]),
            Section::make(admin_field('validity'))->columns(2)->schema([
                DatePicker::make('valid_from')->label(admin_field('valid_from'))->required()->native(false)->default(today(config('qdealme.timezone'))),
                DatePicker::make('valid_until')->label(admin_field('valid_until'))->required()->native(false)->afterOrEqual('valid_from'),
                Toggle::make('is_sponsored')->label(admin_field('sponsored'))->live(),
                DatePicker::make('sponsored_until')->label(admin_field('sponsored_until'))->native(false)->visible(fn (Get $get) => (bool) $get('is_sponsored')),
            ]),
            Section::make(admin_field('targeting'))->columns(2)->schema([
                Select::make('cities')->label(admin_field('cities'))->relationship('cities', 'name_en')->multiple()->preload()->required(),
                Select::make('branches')->label(admin_field('branches'))->multiple()
                    ->relationship('branches', 'name_en', fn (Builder $query, Get $get) => $query->where('retailer_id', $get('retailer_id')))
                    ->preload(),
            ]),
            Section::make(admin_field('links'))->columns(2)->schema([
                TextInput::make('shop_online_url')->label(admin_field('shop_online_url'))->url()->maxLength(255),
                TextInput::make('whatsapp_order_url')->label(admin_field('whatsapp_order_url'))->url()->maxLength(255),
                TextInput::make('source_post_url')->label(admin_field('source_post_url'))->url()->maxLength(2048)->columnSpanFull()
                    ->visible(fn (?Flyer $record) => filled($record?->source_post_url))
                    ->suffixAction(static::openLinkAction()),
            ]),
            Section::make(admin_field('files'))->schema([
                FileUpload::make('upload_files')->label(admin_field('upload'))
                    ->helperText(FlyerImageProcessor::canRasterizePdf() ? __('admin.upload_help') : __('admin.upload_help_images_only'))
                    ->disk('local')->directory('uploads/flyers')->visibility('private')
                    ->multiple()->reorderable()->appendFiles()
                    ->acceptedFileTypes(FlyerImageProcessor::canRasterizePdf()
                        ? ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']
                        : ['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(config('qdealme.max_upload_mb') * 1024)
                    ->required(fn (?Flyer $record) => $record === null),
                Placeholder::make('processing_error')->label(admin_field('processing_error'))
                    ->visible(fn (?Flyer $record) => filled($record?->processing_error))
                    ->content(fn (?Flyer $record) => new HtmlString('<span style="color:#b3261e">'.e((string) $record?->processing_error).'</span>')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['retailer', 'cover', 'cities']))
            ->defaultSort('created_at', 'desc')
            ->poll('10s')
            ->columns([
                ImageColumn::make('cover_thumb')->label(admin_field('cover'))
                    ->disk('public')
                    ->state(fn (Flyer $f) => $f->cover ? ($f->cover->derivatives['webp'][320] ?? $f->cover->original_path) : null)
                    ->imageHeight(64)->imageWidth(48),
                TextColumn::make('retailer.name_en')->label(admin_field('retailer'))->searchable()->sortable(),
                TextColumn::make('title_en')->label(admin_field('title_en'))->searchable()->description(fn (Flyer $f) => $f->title_ar)->limit(40),
                TextColumn::make('status')->label(admin_field('status'))->badge()->sortable(),
                TextColumn::make('valid_from')->label(admin_field('validity'))
                    ->formatStateUsing(fn (Flyer $record) => $record->valid_from->translatedFormat('j M').' – '.$record->valid_until->translatedFormat('j M Y'))
                    ->sortable(),
                TextColumn::make('page_count')->label(admin_field('pages'))->numeric()->sortable(),
                TextColumn::make('view_count')->label(admin_field('views'))->numeric()->sortable()->toggleable(),
                IconColumn::make('is_sponsored')->label(admin_field('sponsored'))->boolean()
                    ->trueIcon(Heroicon::OutlinedSparkles)->trueColor('warning')->falseIcon(false)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(admin_field('status'))->options(FlyerStatus::class)->multiple(),
                SelectFilter::make('retailer_id')->label(admin_field('retailer'))->relationship('retailer', 'name_en')->searchable()->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    static::approveAction(),
                    static::reprocessAction(),
                    Action::make('archive')->label(__('admin.archive'))->icon(Heroicon::OutlinedArchiveBox)->color('gray')
                        ->visible(fn (Flyer $f) => $f->status !== FlyerStatus::Archived)
                        ->requiresConfirmation()
                        ->action(fn (Flyer $f) => $f->forceFill(['status' => FlyerStatus::Archived])->save()),
                    Action::make('view')->label(admin_field('view_on_site'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->visible(fn (Flyer $f) => $f->status->isPublic() && $f->cities->isNotEmpty())
                        ->url(fn (Flyer $f) => route('flyers.show', [
                            'locale' => 'ar', 'country' => config('qdealme.default_country'),
                            'city' => $f->cities->first()->slug, 'retailer' => $f->retailer->slug, 'flyer' => $f->routeSegment(),
                        ]))
                        ->openUrlInNewTab(),
                ]),
                EditAction::make(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    public static function approvedSourceOptions(mixed $retailerId): array
    {
        return FlyerSource::query()
            ->approved()
            ->where('retailer_id', $retailerId)
            ->get()
            ->mapWithKeys(fn (FlyerSource $s) => [$s->id => $s->type->label().' · #'.$s->id.($s->permission_contact ? ' · '.$s->permission_contact : '')])
            ->all();
    }

    public static function approvedSourceRule(mixed $retailerId): Exists
    {
        return Rule::exists('flyer_sources', 'id')
            ->where('license_status', LicenseStatus::Approved->value)
            ->where('retailer_id', $retailerId);
    }

    /**
     * Import a flyer that a store posted on its own social media page or
     * website: staff paste the post link and drop in the post's images. The
     * store, the source and the offer dates are filled in automatically.
     */
    public static function importFromPostAction(): Action
    {
        return Action::make('importPost')
            ->label(__('admin.import.action'))
            ->icon(Heroicon::OutlinedPhoto)
            ->color('gray')
            ->visible(fn (): bool => static::canCreate())
            ->modalIcon(Heroicon::OutlinedPhoto)
            ->modalHeading(__('admin.import.heading'))
            ->modalDescription(__('admin.import.description'))
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel(__('admin.import.submit'))
            ->schema([
                TextInput::make('post_url')->label(admin_field('source_post_url'))
                    ->url()->required()->maxLength(2048)
                    ->placeholder('https://www.facebook.com/…')
                    ->helperText(__('admin.import.link_help'))
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($source = ImportFlyerFromPost::guessSource($state)) {
                            $set('retailer_id', $source->retailer_id);
                            $set('flyer_source_id', $source->id);
                        }
                    })
                    ->suffixAction(static::openLinkAction()),
                Section::make()->columns(2)->schema([
                    Select::make('retailer_id')->label(admin_field('retailer'))
                        ->options(fn () => Retailer::query()
                            ->whereHas('sources', fn (Builder $query) => $query->approved())
                            ->orderBy('name_en')
                            ->pluck('name_en', 'id'))
                        ->searchable()->required()->live()
                        ->helperText(__('admin.import.store_help'))
                        ->afterStateUpdated(fn (mixed $state, Set $set) => $set('flyer_source_id', ImportFlyerFromPost::bestSourceFor(filled($state) ? (int) $state : null)?->id)),
                    Select::make('flyer_source_id')->label(admin_field('source'))
                        ->options(fn (Get $get) => static::approvedSourceOptions($get('retailer_id')))
                        ->rule(fn (Get $get) => static::approvedSourceRule($get('retailer_id')))
                        ->required()->native(false),
                ]),
                Callout::make(__('admin.import.connected'))
                    ->description(__('admin.import.connected_hint'))
                    ->icon(Heroicon::OutlinedBolt)
                    ->success()
                    ->visible(fn (Get $get): bool => static::connectedSource($get('flyer_source_id')) !== null)
                    ->actions([
                        Action::make('checkNow')
                            ->label(__('admin.import.check_now'))
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->action(function (Get $get, Component $livewire): void {
                                if ($source = static::connectedSource($get('flyer_source_id'))) {
                                    FlyerSourceResource::pull($source, $livewire);
                                }
                            }),
                    ]),
                Callout::make(__('admin.import.automate'))
                    ->description(__('admin.import.automate_hint'))
                    ->icon(Heroicon::OutlinedSparkles)
                    ->info()
                    ->visible(fn (Get $get): bool => filled($get('flyer_source_id')) && static::connectedSource($get('flyer_source_id')) === null)
                    ->actions([
                        Action::make('openSources')
                            ->label(__('admin.import.open_sources'))
                            ->url(fn (): string => FlyerSourceResource::getUrl('index'))
                            ->openUrlInNewTab(),
                    ]),
                FileUpload::make('images')->label(admin_field('post_images'))
                    ->helperText(__('admin.import.images_help'))
                    ->disk('local')->directory('uploads/flyers')->visibility('private')
                    ->multiple()->reorderable()->appendFiles()->panelLayout('grid')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxFiles(40)
                    ->maxSize(config('qdealme.max_upload_mb') * 1024)
                    ->required(),
                Textarea::make('caption')->label(admin_field('post_text'))->rows(3)
                    ->helperText(__('admin.import.caption_help'))
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($dates = PostDates::find($state)) {
                            $set('valid_from', $dates['from']->toDateString());
                            $set('valid_until', $dates['until']->toDateString());
                        }
                    }),
                Section::make()->columns(2)->schema([
                    DatePicker::make('valid_from')->label(admin_field('valid_from'))->required()->native(false)
                        ->default(fn () => today(config('qdealme.timezone'))->toDateString()),
                    DatePicker::make('valid_until')->label(admin_field('valid_until'))->required()->native(false)
                        ->default(fn () => today(config('qdealme.timezone'))->addDays(6)->toDateString())
                        ->afterOrEqual('valid_from'),
                    TextInput::make('title_ar')->label(admin_field('title_ar'))->maxLength(160)
                        ->placeholder(fn (Get $get) => static::defaultTitle($get('retailer_id'), 'ar'))
                        ->extraInputAttributes(['dir' => 'rtl']),
                    TextInput::make('title_en')->label(admin_field('title_en'))->maxLength(160)
                        ->placeholder(fn (Get $get) => static::defaultTitle($get('retailer_id'), 'en')),
                ]),
            ])
            ->action(fn (array $data, Component $livewire) => static::importFromPost($data, $livewire));
    }

    /**
     * The chosen source when it is a store page that Meta's API reads by itself.
     */
    public static function connectedSource(mixed $sourceId): ?FlyerSource
    {
        $source = filled($sourceId) ? FlyerSource::query()->find($sourceId) : null;

        return $source?->type === SourceType::SocialMedia && PullAllFeeds::isPullable($source) ? $source : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function importFromPost(array $data, Component $livewire): void
    {
        $paths = array_values(array_filter((array) ($data['images'] ?? [])));
        $source = FlyerSource::query()->approved()->where('retailer_id', $data['retailer_id'] ?? null)->find($data['flyer_source_id'] ?? null);
        $timezone = config('qdealme.timezone');

        try {
            if ($source === null) {
                throw PostImportException::because('not_approved');
            }

            $flyer = app(ImportFlyerFromPost::class)->handle($source, $paths, $data['post_url'] ?? null, [
                'title_en' => $data['title_en'] ?? null,
                'title_ar' => $data['title_ar'] ?? null,
                'valid_from' => filled($data['valid_from'] ?? null) ? CarbonImmutable::parse($data['valid_from'], $timezone) : null,
                'valid_until' => filled($data['valid_until'] ?? null) ? CarbonImmutable::parse($data['valid_until'], $timezone) : null,
            ]);
        } catch (PostImportException $e) {
            // Nothing was imported, so the uploaded copies are not needed.
            Storage::disk('local')->delete($paths);

            $notification = Notification::make()->warning()->title(__('admin.import.failed'))->body($e->getMessage())->persistent();

            if ($e->existing !== null) {
                $notification->actions([
                    Action::make('openExisting')->label(__('admin.import.open_existing'))->button()
                        ->url(static::getUrl('edit', ['record' => $e->existing])),
                ]);
            }

            $notification->send();

            return;
        }

        Notification::make()->success()->title(__('admin.import.created'))->body(__('admin.import.created_hint'))->send();
        $livewire->redirect(static::getUrl('edit', ['record' => $flyer]));
    }

    /**
     * The title a draft gets when staff leave it empty, shown as the hint.
     */
    private static function defaultTitle(mixed $retailerId, string $locale): ?string
    {
        $retailer = filled($retailerId) ? Retailer::query()->find($retailerId) : null;

        return $retailer ? __('feed.default_title', ['store' => $locale === 'ar' ? $retailer->name_ar : $retailer->name_en], $locale) : null;
    }

    private static function openLinkAction(): Action
    {
        return Action::make('openLink')
            ->label(__('admin.import.open_link'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (?string $state) => filled($state) && str_starts_with($state, 'https://') ? $state : null, shouldOpenInNewTab: true)
            ->visible(fn (?string $state) => filled($state) && str_starts_with($state, 'https://'));
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('admin.approve'))
            ->icon(Heroicon::OutlinedCheckBadge)->color('success')
            ->visible(fn (Flyer $f) => in_array($f->status, [FlyerStatus::InReview, FlyerStatus::Draft], true) && $f->page_count > 0)
            ->requiresConfirmation()
            ->action(function (Flyer $f, PublishFlyer $publish): void {
                $publish->handle($f);
                Notification::make()->success()
                    ->title($f->status === FlyerStatus::Published ? __('admin.published_notice') : __('admin.approved_notice'))
                    ->send();
            });
    }

    public static function reprocessAction(): Action
    {
        return Action::make('reprocess')
            ->label(__('admin.reprocess'))
            ->icon(Heroicon::OutlinedArrowPath)->color('gray')
            ->visible(fn (Flyer $f) => filled($f->upload_files) && $f->status !== FlyerStatus::Processing)
            ->requiresConfirmation()
            ->action(function (Flyer $f): void {
                ProcessFlyerUpload::dispatch($f->id);
                Notification::make()->info()->title(__('admin.reprocess_notice'))->send();
            });
    }

    public static function getRelations(): array
    {
        return [OffersRelationManager::class];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlyers::route('/'),
            'create' => CreateFlyer::route('/create'),
            'edit' => EditFlyer::route('/{record}/edit'),
        ];
    }
}
