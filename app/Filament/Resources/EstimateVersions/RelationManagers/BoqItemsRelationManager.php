<?php

declare(strict_types=1);

namespace App\Filament\Resources\EstimateVersions\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\BoqItem;
use App\Services\Acquisition\BoqItemService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BoqItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'boqItems';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedQueueList;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('boq_item.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('boq_item.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('item_code')
                            ->label(__('boq_item.fields.item_code'))
                            ->required()
                            ->maxLength(32),
                        TextInput::make('description')
                            ->label(__('boq_item.fields.description'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('quantity')
                            ->label(__('boq_item.fields.quantity'))
                            ->numeric()
                            ->step('0.000001')
                            ->minValue(1.0E-6)
                            ->default(1)
                            ->required(),
                        Select::make('uom_id')
                            ->label(__('boq_item.fields.uom'))
                            ->relationship('uom', 'name_tr')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        TextInput::make('unit_price')
                            ->label(__('boq_item.fields.unit_price'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0),
                        TextInput::make('sort_order')
                            ->label(__('boq_item.fields.sort_order'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('boq_item.label'))
            ->heading(__('boq_item.relation.title'))
            ->recordTitleAttribute('item_code')
            ->columns([
                TextColumn::make('item_code')
                    ->label(__('boq_item.fields.item_code')),
                TextColumn::make('description')
                    ->label(__('boq_item.fields.description'))
                    ->limit(40),
                TextColumn::make('quantity')
                    ->label(__('boq_item.fields.quantity')),
                TextColumn::make('uom.symbol')
                    ->label(__('boq_item.fields.uom')),
                TextColumn::make('unit_price')
                    ->label(__('boq_item.fields.unit_price'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['estimate_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(BoqItemService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (BoqItem $record, array $data): Model {
                        try {
                            return app(BoqItemService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (BoqItem $record): bool {
                        try {
                            return app(BoqItemService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('boq_item.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedQueueList);
    }
}
