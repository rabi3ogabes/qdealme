<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Concerns\AdminNavigation;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    use AdminNavigation;

    protected static ?string $model = Category::class;

    protected static string $groupKey = 'catalog';

    protected static string $labelKey = 'category';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name_ar')->label(admin_field('name_ar'))->required()->maxLength(120),
            TextInput::make('name_en')->label(admin_field('name_en'))->required()->maxLength(120),
            TextInput::make('slug')->label(admin_field('slug'))->required()->alphaDash()->maxLength(96)->unique(ignoreRecord: true),
            Select::make('parent_id')->label(admin_field('parent'))->relationship('parent', 'name_en', fn ($query) => $query->whereNull('parent_id')),
            TextInput::make('sort')->label(admin_field('sort'))->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name_en')->label(admin_field('name_en'))->description(fn (Category $c) => $c->name_ar)->searchable(),
                TextColumn::make('parent.name_en')->label(admin_field('parent'))->badge(),
                TextColumn::make('slug')->label(admin_field('slug')),
                TextColumn::make('offers_count')->counts('offers')->label(__('admin.models.offer.many')),
                TextColumn::make('sort')->label(admin_field('sort'))->sortable(),
            ])
            ->filters([SelectFilter::make('parent_id')->label(admin_field('parent'))->relationship('parent', 'name_en')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
