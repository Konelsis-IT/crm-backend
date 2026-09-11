<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProposalVersions\RelationManagers;

use App\Enums\Acquisition\EstimateVersionStatus;
use App\Exceptions\AbstractException;
use App\Filament\Resources\EstimateVersions\EstimateVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\EstimateVersion;
use App\Query\Reference\ReferenceOptions;
use App\Services\Acquisition\EstimateVersionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use Illuminate\Support\Facades\Gate;

class EstimateVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'estimateVersions';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCalculator;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('estimate_version.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('estimate_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('currency_code')
                            ->label(__('estimate_version.fields.currency'))
                            ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                            ->default('TRY')
                            ->searchable()
                            ->required()
                            ->native(false),
                        TextInput::make('target_margin_pct')
                            ->label(__('estimate_version.fields.target_margin_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100),
                        Textarea::make('notes')
                            ->label(__('estimate_version.fields.notes'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('estimate_version.label'))
            ->heading(__('estimate_version.relation.title'))
            ->recordTitleAttribute('version_no')
            ->columns([
                TextColumn::make('version_no')
                    ->label(__('estimate_version.fields.version_no'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('estimate_version.fields.status'))
                    ->badge(),
                TextColumn::make('total_cost')
                    ->label(__('estimate_version.fields.total_cost'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('total_price')
                    ->label(__('estimate_version.fields.total_price'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('target_margin_pct')
                    ->label(__('estimate_version.fields.target_margin_pct'))
                    ->suffix('%')
                    ->placeholder('-'),
                TextColumn::make('preparer.full_name')
                    ->label(__('estimate_version.fields.preparer')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['proposal_version_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(EstimateVersionService::class)->create($data);
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
                    ->url(fn (EstimateVersion $record): string => EstimateVersionResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->using(function (EstimateVersion $record, array $data): Model {
                        try {
                            return app(EstimateVersionService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                ActionGroup::make($this->statusActions())
                    ->label(__('estimate_version.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('version_no', 'desc')
            ->emptyStateHeading(__('estimate_version.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCalculator);
    }

    /**
     * Izin verilen her hedef durum icin ayri islem (docs/planning/14).
     *
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];

        foreach (EstimateVersionStatus::cases() as $target) {
            if (in_array($target, [EstimateVersionStatus::Superseded], true)) {
                continue;
            }

            $actions[] = Action::make('status_'.$target->value)
                ->label(__('estimate_version.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->visible(fn (EstimateVersion $record): bool => Gate::allows('update', $record)
                    && $record->status->canTransitionTo($target))
                ->action(function (EstimateVersion $record, array $data) use ($target): void {
                    try {
                        app(EstimateVersionService::class)->changeStatus($record, $target);
                        DomainNotifications::success(__('estimate_version.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
