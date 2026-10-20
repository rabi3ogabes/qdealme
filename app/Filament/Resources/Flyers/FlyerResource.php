<?php

namespace App\Filament\Resources\Flyers;

use App\Actions\Flyers\PublishFlyer;
use App\Enums\FlyerStatus;
use App\Enums\LicenseStatus;
use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\Flyers\Pages\CreateFlyer;
use App\Filament\Resources\Flyers\Pages\EditFlyer;
use App\Filament\Resources\Flyers\Pages\ListFlyers;
use App\Filament\Resources\Flyers\RelationManagers\OffersRelationManager;
use App\Jobs\ProcessFlyerUpload;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Services\FlyerImageProcessor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
                    ->options(fn (Get $get) => FlyerSource::query()
                        ->approved()
                        ->where('retailer_id', $get('retailer_id'))
                        ->get()
                        ->mapWithKeys(fn (FlyerSource $s) => [$s->id => $s->type->label().' · #'.$s->id.($s->permission_contact ? ' · '.$s->permission_contact : '')]))
                    ->rule(fn (Get $get) => Rule::exists('flyer_sources', 'id')
                        ->where('license_status', LicenseStatus::Approved->value)
                        ->where('retailer_id', $get('retailer_id')))
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
                TextColumn::make('valid_from')->label(admin_field('validity'))->date('j M')
                    ->description(fn (Flyer $f) => '→ '.$f->valid_until->format('j M Y'))->sortable(),
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
