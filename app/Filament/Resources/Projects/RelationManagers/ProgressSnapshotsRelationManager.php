<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Project\ProgressSource;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProgressSnapshot;
use App\Services\Project\ProgressSnapshotService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
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

class ProgressSnapshotsRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'progressSnapshots';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedChartBar;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('progress_snapshot.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('progress_snapshot.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        DateTimePicker::make('snapshot_at')
                            ->label(__('progress_snapshot.fields.snapshot_at')),
                        Select::make('source')
                            ->label(__('progress_snapshot.fields.source'))
                            ->options(ProgressSource::class)
                            ->default(ProgressSource::Manual->value)
                            ->required()
                            ->native(false),
                        TextInput::make('physical_progress_pct')
                            ->label(__('progress_snapshot.fields.physical_progress_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required(),
                        TextInput::make('planned_progress_pct')
                            ->label(__('progress_snapshot.fields.planned_progress_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100),
                        TextInput::make('cost_progress_pct')
                            ->label(__('progress_snapshot.fields.cost_progress_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('progress_snapshot.label'))
            ->heading(__('progress_snapshot.relation.title'))
            ->recordTitleAttribute('snapshot_at')
            ->columns([
                TextColumn::make('snapshot_at')
                    ->label(__('progress_snapshot.fields.snapshot_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('source')
                    ->label(__('progress_snapshot.fields.source'))
                    ->badge(),
                TextColumn::make('physical_progress_pct')
                    ->label(__('progress_snapshot.fields.physical_progress_pct'))
                    ->suffix('%'),
                TextColumn::make('planned_progress_pct')
                    ->label(__('progress_snapshot.fields.planned_progress_pct'))
                    ->suffix('%')
                    ->placeholder('-'),
                TextColumn::make('cost_progress_pct')
                    ->label(__('progress_snapshot.fields.cost_progress_pct'))
                    ->suffix('%')
                    ->placeholder('-'),
                TextColumn::make('reporter.full_name')
                    ->label(__('progress_snapshot.fields.reporter')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProgressSnapshotService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                
            ])
            ->toolbarActions([])
            ->defaultSort('snapshot_at', 'desc')
            ->emptyStateHeading(__('progress_snapshot.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedChartBar);
    }
}
