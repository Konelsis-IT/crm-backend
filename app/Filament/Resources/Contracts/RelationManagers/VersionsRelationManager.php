<?php

declare(strict_types=1);

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Enums\Acquisition\ContractVersionStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\ContractVersions\ContractVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ContractVersion;
use App\Query\Reference\ReferenceOptions;
use App\Services\Acquisition\ContractVersionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Support\Facades\Gate;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedDocumentDuplicate;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('contract_version.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('contract_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('locale')
                            ->label(__('contract_version.fields.locale'))
                            ->options(['tr' => 'Türkçe', 'en' => 'English'])
                            ->default('tr')
                            ->required()
                            ->native(false),
                        Select::make('currency_code')
                            ->label(__('contract_version.fields.currency'))
                            ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                            ->default('TRY')
                            ->searchable()
                            ->required()
                            ->native(false),
                        TextInput::make('contract_value')
                            ->label(__('contract_version.fields.contract_value'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0),
                        DatePicker::make('effective_from')
                            ->label(__('contract_version.fields.effective_from'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('effective_until')
                            ->label(__('contract_version.fields.effective_until'))
                            ->displayFormat('d.m.Y'),
                        Textarea::make('summary')
                            ->label(__('contract_version.fields.summary'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('contract_version.label'))
            ->heading(__('contract_version.relation.title'))
            ->recordTitleAttribute('version_no')
            ->columns([
                TextColumn::make('version_no')
                    ->label(__('contract_version.fields.version_no'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('contract_version.fields.status'))
                    ->badge(),
                TextColumn::make('contract_value')
                    ->label(__('contract_version.fields.contract_value'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('effective_from')
                    ->label(__('contract_version.fields.effective_from'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('approved_at')
                    ->label(__('contract_version.fields.approved_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('executed_at')
                    ->label(__('contract_version.fields.executed_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['contract_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ContractVersionService::class)->create($data);
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
                    ->url(fn (ContractVersion $record): string => ContractVersionResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (ContractVersion $record, array $data): Model {
                        try {
                            return app(ContractVersionService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                ActionGroup::make($this->statusActions())
                    ->label(__('contract_version.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('version_no', 'desc')
            ->emptyStateHeading(__('contract_version.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentDuplicate);
    }

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];

        foreach (ContractVersionStatus::cases() as $target) {
            if (in_array($target, [ContractVersionStatus::Superseded], true)) {
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('contract_version.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->visible(fn (ContractVersion $record): bool => Gate::allows('update', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (ContractVersion $record, array $data) use ($target): void {
                    try {
                        app(ContractVersionService::class)->changeStatus($record, $target);
                        DomainNotifications::success(__('contract_version.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
