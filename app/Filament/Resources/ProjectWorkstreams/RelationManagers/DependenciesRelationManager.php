<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectWorkstreams\RelationManagers;

use App\Enums\Project\DependencyStatus;
use App\Enums\Project\DependencyType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\WorkstreamDependency;
use App\Services\Project\WorkstreamDependencyService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
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

class DependenciesRelationManager extends RelationManager
{
    protected static string $relationship = 'predecessorDependencies';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedArrowsRightLeft;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('workstream_dependency.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('workstream_dependency.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('predecessor_workstream_id')
                            ->label(__('workstream_dependency.fields.predecessor_workstream'))
                            ->relationship(
                            'predecessor',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->project_id),
                        )
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Project\ProjectWorkstream $record): string => $record->group->name_tr)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('dependency_type')
                            ->label(__('workstream_dependency.fields.dependency_type'))
                            ->options(DependencyType::class)
                            ->default(DependencyType::FS->value)
                            ->required()
                            ->native(false),
                        TextInput::make('lag_days')
                            ->label(__('workstream_dependency.fields.lag_days'))
                            ->numeric()
                            ->minValue(-365)
                            ->maxValue(365)
                            ->default(0),
                        Toggle::make('is_hard')
                            ->label(__('workstream_dependency.fields.is_hard'))
                            ->default(true),
                        Select::make('status')
                            ->label(__('workstream_dependency.fields.status'))
                            ->options(DependencyStatus::class)
                            ->default(DependencyStatus::Active->value)
                            ->required()
                            ->native(false),
                        Textarea::make('waiver_reason')
                            ->label(__('workstream_dependency.fields.waiver_reason'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('workstream_dependency.label'))
            ->heading(__('workstream_dependency.relation.title'))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('predecessor.group.name_tr')
                    ->label(__('workstream_dependency.fields.predecessor')),
                TextColumn::make('dependency_type')
                    ->label(__('workstream_dependency.fields.dependency_type'))
                    ->badge(),
                TextColumn::make('lag_days')
                    ->label(__('workstream_dependency.fields.lag_days')),
                IconColumn::make('is_hard')
                    ->label(__('workstream_dependency.fields.is_hard'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('workstream_dependency.fields.status'))
                    ->badge(),
                TextColumn::make('waiver.full_name')
                    ->label(__('workstream_dependency.fields.waiver'))
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['successor_workstream_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(WorkstreamDependencyService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (WorkstreamDependency $record, array $data): Model {
                        try {
                            return app(WorkstreamDependencyService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (WorkstreamDependency $record): bool {
                        try {
                            return app(WorkstreamDependencyService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('workstream_dependency.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft);
    }
}
