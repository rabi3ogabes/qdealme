<?php

namespace App\Filament\Resources\Retailers\RelationManagers;

use App\Models\City;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.models.branch.many');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name_ar')->label(admin_field('name_ar'))->required()->maxLength(160),
            TextInput::make('name_en')->label(admin_field('name_en'))->required()->maxLength(160),
            Select::make('city_id')->label(admin_field('city'))->options(fn () => City::query()->orderBy('sort')->pluck('name_en', 'id'))->required()->searchable(),
            TextInput::make('phone')->label(admin_field('phone'))->tel()->maxLength(32),
            TextInput::make('address_ar')->label(admin_field('address_ar'))->maxLength(255),
            TextInput::make('address_en')->label(admin_field('address_en'))->maxLength(255),
            TextInput::make('lat')->label(admin_field('lat'))->numeric()->minValue(-90)->maxValue(90),
            TextInput::make('lng')->label(admin_field('lng'))->numeric()->minValue(-180)->maxValue(180),
            TextInput::make('opening_hours.ar')->label(admin_field('hours_ar'))->maxLength(120),
            TextInput::make('opening_hours.en')->label(admin_field('hours_en'))->maxLength(120),
            Toggle::make('is_active')->label(admin_field('active'))->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_en')
            ->columns([
                TextColumn::make('name_en')->label(admin_field('name_en'))->description(fn ($record) => $record->name_ar)->searchable(),
                TextColumn::make('city.name_en')->label(admin_field('city')),
                TextColumn::make('phone')->label(admin_field('phone')),
                IconColumn::make('is_active')->label(admin_field('active'))->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
