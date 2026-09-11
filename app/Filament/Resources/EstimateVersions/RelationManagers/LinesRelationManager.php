<?php

declare(strict_types=1);

namespace App\Filament\Resources\EstimateVersions\RelationManagers;

use App\Enums\Acquisition\CostCategory;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\EstimateLine;
use App\Services\Acquisition\EstimateLineService;
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

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedListBullet;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('estimate_line.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('estimate_line.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('line_code')
                            ->label(__('estimate_line.fields.line_code'))
                            ->required()
                            ->maxLength(32),
                        Select::make('cost_type')
                            ->label(__('estimate_line.fields.cost_type'))
                            ->options(CostCategory::class)
                            ->default(CostCategory::Material->value)
                            ->required()
                            ->native(false),
                        TextInput::make('description')
                            ->label(__('estimate_line.fields.description'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('quantity')
                            ->label(__('estimate_line.fields.quantity'))
                            ->numeric()
                            ->step('0.000001')
                            ->minValue(1.0E-6)
                            ->default(1)
                            ->required(),
                        Select::make('uom_id')
                            ->label(__('estimate_line.fields.uom'))
                            ->relationship('uom', 'name_tr')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        TextInput::make('unit_cost')
                            ->label(__('estimate_line.fields.unit_cost'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->required(),
                        TextInput::make('unit_price')
                            ->label(__('estimate_line.fields.unit_price'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0),
                        Select::make('parent_line_id')
                            ->label(__('estimate_line.fields.parent_line'))
                            ->relationship(
                            'parent',
                            'line_code',
                            modifyQueryUsing: fn ($query) => $query->where('estimate_version_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        TextInput::make('wbs_hint')
                            ->label(__('estimate_line.fields.wbs_hint'))
                            ->maxLength(32),
                        TextInput::make('sort_order')
                            ->label(__('estimate_line.fields.sort_order'))
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
            ->modelLabel(__('estimate_line.label'))
            ->heading(__('estimate_line.relation.title'))
            ->recordTitleAttribute('line_code')
            ->columns([
                TextColumn::make('line_code')
                    ->label(__('estimate_line.fields.line_code')),
                TextColumn::make('cost_type')
                    ->label(__('estimate_line.fields.cost_type'))
                    ->badge(),
                TextColumn::make('description')
                    ->label(__('estimate_line.fields.description'))
                    ->limit(40),
                TextColumn::make('quantity')
                    ->label(__('estimate_line.fields.quantity')),
                TextColumn::make('uom.symbol')
                    ->label(__('estimate_line.fields.uom')),
                TextColumn::make('unit_cost')
                    ->label(__('estimate_line.fields.unit_cost'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('line_total_cost')
                    ->label(__('estimate_line.fields.line_total_cost'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('unit_price')
                    ->label(__('estimate_line.fields.unit_price'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['estimate_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(EstimateLineService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (EstimateLine $record, array $data): Model {
                        try {
                            return app(EstimateLineService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (EstimateLine $record): bool {
                        try {
                            return app(EstimateLineService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('estimate_line.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedListBullet);
    }
}
