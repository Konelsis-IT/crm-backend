<?php

declare(strict_types=1);

namespace App\Filament\Resources\Proposals\RelationManagers;

use App\Enums\Acquisition\ProposalVersionStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\ProposalVersions\ProposalVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ProposalVersion;
use App\Query\Reference\ReferenceOptions;
use App\Services\Acquisition\ProposalVersionService;
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
use Filament\Forms\Components\Toggle;
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
        return __('proposal_version.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('proposal_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('locale')
                            ->label(__('proposal_version.fields.locale'))
                            ->options(['tr' => 'Türkçe', 'en' => 'English'])
                            ->default('tr')
                            ->required()
                            ->native(false),
                        Select::make('currency_code')
                            ->label(__('proposal_version.fields.currency'))
                            ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                            ->default('TRY')
                            ->searchable()
                            ->required()
                            ->native(false),
                        TextInput::make('total_price')
                            ->label(__('proposal_version.fields.total_price'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0),
                        TextInput::make('margin_pct')
                            ->label(__('proposal_version.fields.margin_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100),
                        DatePicker::make('validity_until')
                            ->label(__('proposal_version.fields.validity_until'))
                            ->displayFormat('d.m.Y'),
                        Toggle::make('is_critical_route')
                            ->label(__('proposal_version.fields.is_critical_route')),
                        Textarea::make('summary')
                            ->label(__('proposal_version.fields.summary'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('proposal_version.label'))
            ->heading(__('proposal_version.relation.title'))
            ->recordTitleAttribute('version_no')
            ->columns([
                TextColumn::make('version_no')
                    ->label(__('proposal_version.fields.version_no'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('proposal_version.fields.status'))
                    ->badge(),
                TextColumn::make('total_price')
                    ->label(__('proposal_version.fields.total_price'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('margin_pct')
                    ->label(__('proposal_version.fields.margin_pct'))
                    ->suffix('%')
                    ->placeholder('-'),
                TextColumn::make('validity_until')
                    ->label(__('proposal_version.fields.validity_until'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('preparer.full_name')
                    ->label(__('proposal_version.fields.preparer')),
                TextColumn::make('submitted_at')
                    ->label(__('proposal_version.fields.submitted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['proposal_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProposalVersionService::class)->create($data);
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
                    ->url(fn (ProposalVersion $record): string => ProposalVersionResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (ProposalVersion $record, array $data): Model {
                        try {
                            return app(ProposalVersionService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                ActionGroup::make($this->statusActions())
                    ->label(__('proposal_version.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('version_no', 'desc')
            ->emptyStateHeading(__('proposal_version.relation.empty'))
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

        foreach (ProposalVersionStatus::cases() as $target) {
            if (in_array($target, [ProposalVersionStatus::Superseded], true)) {
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('proposal_version.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->visible(fn (ProposalVersion $record): bool => Gate::allows('update', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (ProposalVersion $record, array $data) use ($target): void {
                    try {
                        app(ProposalVersionService::class)->changeStatus($record, $target);
                        DomainNotifications::success(__('proposal_version.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
