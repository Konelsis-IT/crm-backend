<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Project\ChangeStatus;
use App\Enums\Project\ChangeType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectChange;
use App\Query\Reference\ReferenceOptions;
use App\Services\Project\ProjectChangeService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'changes';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedArrowPathRoundedSquare;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_change.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_change.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('title')
                            ->label(__('project_change.fields.title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('project_change.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('change_type')
                            ->label(__('project_change.fields.change_type'))
                            ->options(ChangeType::class)
                            ->default(ChangeType::Scope->value)
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label(__('project_change.fields.status'))
                            ->options(ChangeStatus::class)
                            ->default(ChangeStatus::Draft->value)
                            ->required()
                            ->native(false),
                        TextInput::make('impact_cost')
                            ->label(__('project_change.fields.impact_cost'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0),
                        Select::make('currency_code')
                            ->label(__('project_change.fields.currency'))
                            ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                            ->searchable()
                            ->native(false),
                        TextInput::make('impact_days')
                            ->label(__('project_change.fields.impact_days'))
                            ->numeric()
                            ->minValue(-3650)
                            ->maxValue(3650),
                        Toggle::make('affects_baseline')
                            ->label(__('project_change.fields.affects_baseline')),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('project_change.label'))
            ->heading(__('project_change.relation.title'))
            ->recordTitleAttribute('change_no')
            ->columns([
                TextColumn::make('change_no')
                    ->label(__('project_change.fields.change_no')),
                TextColumn::make('title')
                    ->label(__('project_change.fields.title'))
                    ->limit(40),
                TextColumn::make('change_type')
                    ->label(__('project_change.fields.change_type'))
                    ->badge(),
                TextColumn::make('impact_cost')
                    ->label(__('project_change.fields.impact_cost'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('impact_days')
                    ->label(__('project_change.fields.impact_days'))
                    ->placeholder('-'),
                IconColumn::make('affects_baseline')
                    ->label(__('project_change.fields.affects_baseline'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('project_change.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProjectChangeService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProjectChange $record, array $data): Model {
                        try {
                            return app(ProjectChangeService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('requested_at', 'desc')
            ->emptyStateHeading(__('project_change.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedArrowPathRoundedSquare);
    }
}
