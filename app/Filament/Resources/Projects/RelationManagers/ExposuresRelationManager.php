<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Project\ExposureKind;
use App\Enums\Project\ExposureStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\CommercialExposure;
use App\Query\Reference\ReferenceOptions;
use App\Services\Project\CommercialExposureService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ExposuresRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'exposures';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedBanknotes;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('commercial_exposure.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('commercial_exposure.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('exposure_kind')
                            ->label(__('commercial_exposure.fields.exposure_kind'))
                            ->options(ExposureKind::class)
                            ->default(ExposureKind::Claim->value)
                            ->required()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('commercial_exposure.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('exposure_amount')
                            ->label(__('commercial_exposure.fields.exposure_amount'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->required(),
                        Select::make('currency_code')
                            ->label(__('commercial_exposure.fields.currency'))
                            ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                            ->default('TRY')
                            ->searchable()
                            ->required()
                            ->native(false),
                        TextInput::make('probability')
                            ->label(__('commercial_exposure.fields.probability'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(1),
                        Select::make('cbs_node_id')
                            ->label(__('commercial_exposure.fields.cbs_node'))
                            ->relationship(
                            'cbsNode',
                            'cost_code',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('owner_personnel_id')
                            ->label(__('commercial_exposure.fields.owner'))
                            ->relationship('owner', 'full_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('source_delay_event_id')
                            ->label(__('commercial_exposure.fields.source_delay_event'))
                            ->relationship(
                            'sourceDelayEvent',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('source_change_id')
                            ->label(__('commercial_exposure.fields.source_change'))
                            ->relationship(
                            'sourceChange',
                            'change_no',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('status')
                            ->label(__('commercial_exposure.fields.status'))
                            ->options(ExposureStatus::class)
                            ->default(ExposureStatus::Identified->value)
                            ->required()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('commercial_exposure.label'))
            ->heading(__('commercial_exposure.relation.title'))
            ->recordTitleAttribute('exposure_no')
            ->columns([
                TextColumn::make('exposure_no')
                    ->label(__('commercial_exposure.fields.exposure_no')),
                TextColumn::make('exposure_kind')
                    ->label(__('commercial_exposure.fields.exposure_kind'))
                    ->badge(),
                TextColumn::make('description')
                    ->label(__('commercial_exposure.fields.description'))
                    ->limit(40),
                TextColumn::make('exposure_amount')
                    ->label(__('commercial_exposure.fields.exposure_amount'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('probability')
                    ->label(__('commercial_exposure.fields.probability'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('commercial_exposure.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(CommercialExposureService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (CommercialExposure $record, array $data): Model {
                        try {
                            return app(CommercialExposureService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('exposure_amount', 'desc')
            ->emptyStateHeading(__('commercial_exposure.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }
}
