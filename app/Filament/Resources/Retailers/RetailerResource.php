<?php

namespace App\Filament\Resources\Retailers;

use App\Enums\RetailerType;
use App\Enums\SourceType;
use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\FlyerSources\FlyerSourceResource;
use App\Filament\Resources\Retailers\Pages\CreateRetailer;
use App\Filament\Resources\Retailers\Pages\EditRetailer;
use App\Filament\Resources\Retailers\Pages\ListRetailers;
use App\Filament\Resources\Retailers\RelationManagers\BranchesRelationManager;
use App\Models\FlyerSource;
use App\Models\Retailer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Livewire\Component;

class RetailerResource extends Resource
{
    use AdminNavigation;

    protected static ?string $model = Retailer::class;

    protected static string $groupKey = 'content';

    protected static string $labelKey = 'retailer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name_en';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(admin_field('bilingual'))->columns(2)->schema([
                TextInput::make('name_ar')->label(admin_field('name_ar'))->required()->maxLength(160)->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('name_en')->label(admin_field('name_en'))->required()->maxLength(160)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set, ?Retailer $record): void {
                        if ($record === null) {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')->label(admin_field('slug'))->required()->maxLength(96)->alphaDash()->unique(ignoreRecord: true),
                Select::make('type')->label(admin_field('type'))->options(RetailerType::class)->required()->native(false),
                Textarea::make('about_ar')->label(admin_field('about_ar'))->rows(3)->extraInputAttributes(['dir' => 'rtl']),
                Textarea::make('about_en')->label(admin_field('about_en'))->rows(3),
            ]),
            Section::make(admin_field('general'))->columns(2)->schema([
                FileUpload::make('logo_path')->label(admin_field('logo'))->image()->disk('public')->directory('retailers')
                    ->imageEditor()->imageCropAspectRatio('1:1')->maxSize(2048)
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']),
                ColorPicker::make('brand_color')->label(admin_field('brand_color')),
                TextInput::make('website_url')->label(admin_field('website'))->url()->maxLength(255),
                TextInput::make('whatsapp_number')->label(admin_field('whatsapp'))->tel()->maxLength(32),
                TextInput::make('sort')->label(admin_field('sort'))->numeric()->default(0)->minValue(0),
                Toggle::make('is_active')->label(admin_field('active'))->default(true),
                DateTimePicker::make('verified_at')->label(admin_field('verified')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['flyers as live_flyers_count' => fn (Builder $q) => $q->published()]))
            ->defaultSort('sort')
            ->columns([
                ImageColumn::make('logo_path')->label(admin_field('logo'))->disk('public')->circular()->size(40),
                TextColumn::make('name_en')->label(admin_field('name_en'))->searchable()->sortable()
                    ->description(fn (Retailer $r) => $r->name_ar),
                TextColumn::make('type')->label(admin_field('type'))->badge()->sortable(),
                TextColumn::make('live_flyers_count')->label(admin_field('live_flyers'))->badge()->color('success')->sortable(),
                ToggleColumn::make('is_active')->label(admin_field('active')),
                TextColumn::make('sort')->label(admin_field('sort'))->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label(admin_field('type'))->options(RetailerType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('view')->label(admin_field('view_on_site'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Retailer $r) => route('stores.show', ['locale' => 'ar', 'country' => config('qdealme.default_country'), 'city' => config('qdealme.default_city'), 'retailer' => $r]))
                    ->openUrlInNewTab(),
                Action::make('pull')
                    ->label(__('admin.pull_latest'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (Retailer $record) => FlyerSourceResource::canManageLicences() && static::feedSource($record) !== null)
                    ->requiresConfirmation()->modalDescription(__('admin.pull_confirm'))
                    ->action(fn (Retailer $record, Component $livewire) => FlyerSourceResource::pull(static::feedSource($record), $livewire)),
                EditAction::make(),
            ]);
    }

    /**
     * The store's approved feed with an official flyer link, if it has one.
     */
    public static function feedSource(Retailer $retailer): ?FlyerSource
    {
        return $retailer->sources()->approved()
            ->where('type', SourceType::RetailerFeed->value)
            ->whereNotNull('feed_url')
            ->latest('approved_at')
            ->first();
    }

    public static function getRelations(): array
    {
        return [BranchesRelationManager::class];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRetailers::route('/'),
            'create' => CreateRetailer::route('/create'),
            'edit' => EditRetailer::route('/{record}/edit'),
        ];
    }
}
