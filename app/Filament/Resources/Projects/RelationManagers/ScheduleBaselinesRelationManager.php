<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Project\BaselineSource;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ScheduleBaseline;
use App\Services\Project\ScheduleBaselineService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class ScheduleBaselinesRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'scheduleBaselines';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCalendar;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('schedule_baseline.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('schedule_baseline.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('name')
                            ->label(__('schedule_baseline.fields.name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('source')
                            ->label(__('schedule_baseline.fields.source'))
                            ->options(BaselineSource::class)
                            ->default(BaselineSource::Manual->value)
                            ->required()
                            ->native(false),
                        DatePicker::make('planned_start_on')
                            ->label(__('schedule_baseline.fields.planned_start_on'))
                            ->required()
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('planned_finish_on')
                            ->label(__('schedule_baseline.fields.planned_finish_on'))
                            ->required()
                            ->displayFormat('d.m.Y'),
                        Select::make('baseline_document_revision_id')
                            ->label(__('schedule_baseline.fields.baseline_document_revision'))
                            ->relationship('baselineRevision', 'title')
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Document\DocumentRevision $record): string => $record->document->document_no.' Rev.'.$record->revision_code)
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('schedule_baseline.label'))
            ->heading(__('schedule_baseline.relation.title'))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('version_no')
                    ->label(__('schedule_baseline.fields.version_no'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('schedule_baseline.fields.name')),
                TextColumn::make('source')
                    ->label(__('schedule_baseline.fields.source'))
                    ->badge(),
                TextColumn::make('planned_start_on')
                    ->label(__('schedule_baseline.fields.planned_start_on'))
                    ->date('d.m.Y'),
                TextColumn::make('planned_finish_on')
                    ->label(__('schedule_baseline.fields.planned_finish_on'))
                    ->date('d.m.Y'),
                TextColumn::make('status')
                    ->label(__('schedule_baseline.fields.status'))
                    ->badge(),
                TextColumn::make('approved_at')
                    ->label(__('schedule_baseline.fields.approved_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ScheduleBaselineService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ScheduleBaseline $record, array $data): Model {
                        try {
                            return app(ScheduleBaselineService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                Action::make('approve')
                    ->label(__('schedule_baseline.actions.approve'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (ScheduleBaseline $record): bool => $record->status === \App\Enums\Project\BaselineStatus::Draft)
                    ->action(function (ScheduleBaseline $record, array $data): void {
                        try {
                            app(\App\Services\Project\ScheduleBaselineService::class)->approve($record);
                            DomainNotifications::success(__('schedule_baseline.messages.done'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('version_no', 'desc')
            ->emptyStateHeading(__('schedule_baseline.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCalendar);
    }
}
