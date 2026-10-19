<?php

namespace App\Filament\Resources\Cities;

use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\Cities\Pages\ManageCities;
use App\Models\City;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CityResource extends Resource
{
    use AdminNavigation;

    protected static ?string $model = City::class;

    protected static string $groupKey = 'locations';

    protected static string $labelKey = 'city';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('country_id')->label('Country')->relationship('country', 'name_en')->required(),
            TextInput::make('slug')->label(admin_field('slug'))->required()->alphaDash()->maxLength(64),
            TextInput::make('name_ar')->label(admin_field('name_ar'))->required()->maxLength(120),
            TextInput::make('name_en')->label(admin_field('name_en'))->required()->maxLength(120),
            TextInput::make('lat')->label(admin_field('lat'))->numeric(),
            TextInput::make('lng')->label(admin_field('lng'))->numeric(),
            Textarea::make('intro_ar')->label(admin_field('intro_ar'))->rows(3)->maxLength(1000),
            Textarea::make('intro_en')->label(admin_field('intro_en'))->rows(3)->maxLength(1000),
            TextInput::make('sort')->label(admin_field('sort'))->numeric()->default(0),
            Toggle::make('is_active')->label(admin_field('active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name_en')->label(admin_field('name_en'))->description(fn (City $c) => $c->name_ar)->searchable(),
                TextColumn::make('country.name_en')->label('Country'),
                TextColumn::make('slug')->label(admin_field('slug')),
                ToggleColumn::make('is_active')->label(admin_field('active')),
                TextColumn::make('sort')->label(admin_field('sort'))->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCities::route('/')];
    }
}
