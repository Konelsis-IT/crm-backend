<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\RelationManagers;

use App\Enums\Acquisition\HandoffStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\OperationHandoffs\OperationHandoffResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\OperationHandoff;
use App\Services\Acquisition\OperationHandoffService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class OperationHandoffsRelationManager extends RelationManager
{
    protected static string $relationship = 'operationHandoffs';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedArrowRightCircle;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('operation_handoff.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('operation_handoff.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('prepared_by_employee_id')
                            ->label(__('operation_handoff.fields.prepared_by'))
                            ->relationship('preparer', 'full_name')
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
            ->modelLabel(__('operation_handoff.label'))
            ->heading(__('operation_handoff.relation.title'))
            ->recordTitleAttribute('status')
            ->columns([
                TextColumn::make('status')
                    ->label(__('operation_handoff.fields.status'))
                    ->badge(),
                TextColumn::make('preparer.full_name')
                    ->label(__('operation_handoff.fields.preparer')),
                TextColumn::make('acceptedVersion.version_no')
                    ->label(__('operation_handoff.fields.accepted_version'))
                    ->placeholder('-'),
                TextColumn::make('accepted_at')
                    ->label(__('operation_handoff.fields.accepted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['business_case_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(OperationHandoffService::class)->create($data);
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
                    ->url(fn (OperationHandoff $record): string => OperationHandoffResource::getUrl('view', ['record' => $record])),
                ActionGroup::make($this->statusActions())
                    ->label(__('operation_handoff.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('operation_handoff.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedArrowRightCircle);
    }

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];

        foreach (HandoffStatus::cases() as $target) {
            if (in_array($target, [HandoffStatus::Accepted], true)) {
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('operation_handoff.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('operation_handoff.fields.reason'))
                        ->maxLength(500),
                ])
                ->visible(fn (OperationHandoff $record): bool => Gate::allows('update', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (OperationHandoff $record, array $data) use ($target): void {
                    try {
                        app(OperationHandoffService::class)->changeStatus($record, $target, $data['reason'] ?? null);
                        DomainNotifications::success(__('operation_handoff.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
