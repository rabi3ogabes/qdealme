<?php

namespace App\Filament\Resources\FlyerSources;

use App\Actions\Flyers\RevokeFlyerSource;
use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use App\Enums\UserRole;
use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\FlyerSources\Pages\ManageFlyerSources;
use App\Models\FlyerSource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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

    public static function canManageLicences(): bool
    {
        return in_array(auth()->user()?->role, [UserRole::SuperAdmin, UserRole::ContentAdmin], true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('retailer_id')->label(admin_field('retailer'))->relationship('retailer', 'name_en')->searchable()->preload()->required(),
            Select::make('type')->label(admin_field('type'))->options(SourceType::class)->required()->native(false),
            TextInput::make('permission_contact')->label(admin_field('permission_contact'))->maxLength(255)
                ->helperText(__('admin.source_help')),
            FileUpload::make('license_document_path')->label(admin_field('license_document'))
                ->disk('local')->directory('licences')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)->downloadable(),
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
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageFlyerSources::route('/')];
    }
}
