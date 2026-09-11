<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNoticeVersions\RelationManagers;

use App\Enums\Acquisition\TenderDeadlineType;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\TenderDeadline;
use App\Services\Acquisition\TenderDeadlineService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DeadlinesRelationManager extends RelationManager
{
    protected static string $relationship = 'deadlines';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClock;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('tender_deadline.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('tender_deadline.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('deadline_type')
                            ->label(__('tender_deadline.fields.deadline_type'))
                            ->options(TenderDeadlineType::class)
                            ->default(TenderDeadlineType::Submission->value)
                            ->required()
                            ->native(false),
                        DatePicker::make('local_due_date')
                            ->label(__('tender_deadline.fields.local_due_date'))
                            ->required()
                            ->displayFormat('d.m.Y'),
                        TimePicker::make('local_due_time')
                            ->label(__('tender_deadline.fields.local_due_time'))
                            ->seconds(false),
                        TextInput::make('timezone')
                            ->label(__('tender_deadline.fields.timezone'))
                            ->maxLength(64),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('tender_deadline.label'))
            ->heading(__('tender_deadline.relation.title'))
            ->recordTitleAttribute('deadline_type')
            ->columns([
                TextColumn::make('deadline_type')
                    ->label(__('tender_deadline.fields.deadline_type'))
                    ->badge(),
                TextColumn::make('local_due_date')
                    ->label(__('tender_deadline.fields.local_due_date'))
                    ->date('d.m.Y'),
                TextColumn::make('local_due_time')
                    ->label(__('tender_deadline.fields.local_due_time'))
                    ->placeholder('-'),
                TextColumn::make('due_at_utc')
                    ->label(__('tender_deadline.fields.due_at_utc'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['tender_notice_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(TenderDeadlineService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (TenderDeadline $record, array $data): Model {
                        try {
                            return app(TenderDeadlineService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (TenderDeadline $record): bool {
                        try {
                            return app(TenderDeadlineService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('due_at_utc')
            ->emptyStateHeading(__('tender_deadline.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClock);
    }
}
