<?php

namespace App\Filament\Resources\Flyers\RelationManagers;

use App\Filament\Resources\Offers\OfferSchema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OffersRelationManager extends RelationManager
{
    protected static string $relationship = 'offers';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.models.offer.many');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('flyer_page_id')->label(admin_field('page'))
                ->options(fn () => $this->getOwnerRecord()->pages()->pluck('number', 'id')->map(fn ($n) => admin_field('page').' '.$n))
                ->required(),
            ...OfferSchema::components(),
            Fieldset::make('Hotspot (0–1)')->columns(4)->schema([
                TextInput::make('bbox.x')->label('x')->numeric()->minValue(0)->maxValue(1)->required(),
                TextInput::make('bbox.y')->label('y')->numeric()->minValue(0)->maxValue(1)->required(),
                TextInput::make('bbox.w')->label('w')->numeric()->minValue(0.01)->maxValue(1)->required(),
                TextInput::make('bbox.h')->label('h')->numeric()->minValue(0.01)->maxValue(1)->required(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title_en')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['page', 'category']))
            ->columns(OfferSchema::columns(withFlyer: false))
            ->filters([OfferSchema::verificationFilter()])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(fn (array $data) => $data + [
                    'retailer_id' => $this->getOwnerRecord()->retailer_id,
                    'currency' => 'QAR',
                ]),
            ])
            ->recordActions([OfferSchema::verifyAction(), OfferSchema::rejectAction(), EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([OfferSchema::bulkVerify(), OfferSchema::bulkReject()])]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
