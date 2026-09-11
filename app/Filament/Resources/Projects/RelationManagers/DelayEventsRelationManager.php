<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Project\DelayCauseCategory;
use App\Enums\Project\DelayStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\DelayEvents\DelayEventResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\DelayEvent;
use App\Services\Project\DelayEventService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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

class DelayEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'delayEvents';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClock;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('delay_event.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('delay_event.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        DateTimePicker::make('detected_at')
                            ->label(__('delay_event.fields.detected_at')),
                        TextInput::make('delay_days')
                            ->label(__('delay_event.fields.delay_days'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Select::make('cause_category')
                            ->label(__('delay_event.fields.cause_category'))
                            ->options(DelayCauseCategory::class)
                            ->default(DelayCauseCategory::Other->value)
                            ->required()
                            ->native(false),
                        Select::make('workstream_id')
                            ->label(__('delay_event.fields.workstream'))
                            ->relationship(
                            'workstream',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Project\ProjectWorkstream $record): string => $record->group->name_tr)
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Textarea::make('description')
                            ->label(__('delay_event.fields.description'))
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('is_excusable')
                            ->label(__('delay_event.fields.is_excusable')),
                        Select::make('evidence_document_revision_id')
                            ->label(__('delay_event.fields.evidence_document_revision'))
                            ->relationship('evidenceRevision', 'title')
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Document\DocumentRevision $record): string => $record->document->document_no.' Rev.'.$record->revision_code)
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('status')
                            ->label(__('delay_event.fields.status'))
                            ->options(DelayStatus::class)
                            ->default(DelayStatus::Open->value)
                            ->required()
                            ->native(false),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('delay_event.label'))
            ->heading(__('delay_event.relation.title'))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('detected_at')
                    ->label(__('delay_event.fields.detected_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('delay_days')
                    ->label(__('delay_event.fields.delay_days')),
                TextColumn::make('cause_category')
                    ->label(__('delay_event.fields.cause_category'))
                    ->badge(),
                TextColumn::make('description')
                    ->label(__('delay_event.fields.description'))
                    ->limit(40),
                IconColumn::make('is_excusable')
                    ->label(__('delay_event.fields.is_excusable'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('delay_event.fields.status'))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(DelayEventService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('app.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (DelayEvent $record): string => DelayEventResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (DelayEvent $record, array $data): Model {
                        try {
                            return app(DelayEventService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('detected_at', 'desc')
            ->emptyStateHeading(__('delay_event.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClock);
    }
}
