<?php

declare(strict_types=1);

namespace App\Filament\Resources\WbsNodes\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\WbsCbsMapping;
use App\Services\Project\WbsCbsMappingService;
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

class CbsMappingsRelationManager extends RelationManager
{
    protected static string $relationship = 'cbsMappings';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedBanknotes;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('wbs_cbs_mapping.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('wbs_cbs_mapping.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('cbs_node_id')
                            ->label(__('wbs_cbs_mapping.fields.cbs_node'))
                            ->relationship(
                            'cbsNode',
                            'cost_code',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->project_id),
                        )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        TextInput::make('allocation_pct')
                            ->label(__('wbs_cbs_mapping.fields.allocation_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(100)
                            ->required(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('wbs_cbs_mapping.label'))
            ->heading(__('wbs_cbs_mapping.relation.title'))
            ->recordTitleAttribute('allocation_pct')
            ->columns([
                TextColumn::make('cbsNode.cost_code')
                    ->label(__('wbs_cbs_mapping.fields.cbs_node')),
                TextColumn::make('cbsNode.name')
                    ->label(__('wbs_cbs_mapping.fields.name')),
                TextColumn::make('cbsNode.cost_category')
                    ->label(__('wbs_cbs_mapping.fields.cost_category'))
                    ->badge(),
                TextColumn::make('allocation_pct')
                    ->label(__('wbs_cbs_mapping.fields.allocation_pct'))
                    ->suffix('%'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['wbs_node_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(WbsCbsMappingService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (WbsCbsMapping $record, array $data): Model {
                        try {
                            return app(WbsCbsMappingService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (WbsCbsMapping $record): bool {
                        try {
                            return app(WbsCbsMappingService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('wbs_cbs_mapping.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }
}
