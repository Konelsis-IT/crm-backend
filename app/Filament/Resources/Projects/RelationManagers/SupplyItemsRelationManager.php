<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\OpensFromChecklist;
use App\Enums\Project\SupplyItemKind;
use App\Enums\Project\SupplyItemStatus;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectSupplyItem;
use App\Models\Project\ProjectWorkstream;
use App\Models\Project\WbsNode;
use App\Query\Reference\ReferenceOptions;
use App\Services\Project\ProjectSupplyItemService;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Tedarik kalemleri (satin alma adimi). Lojistik ve yazilim sekmeleri ayni
 * tabloyu farkli suzgec ve baslikla gosterir (alt siniflar).
 */
class SupplyItemsRelationManager extends RelationManager
{
    use OpensFromChecklist;

    protected static string $relationship = 'supplyItems';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedShoppingCart;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_supply_item.relation.title');
    }

    /** Alt siniflar tabloyu daraltmak icin override eder. */
    protected function scopeQuery(Builder $query): Builder
    {
        return $query;
    }

    protected function defaultKind(): SupplyItemKind
    {
        return SupplyItemKind::Product;
    }

    protected function heading(): string
    {
        return __('project_supply_item.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_supply_item.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('item_kind')
                        ->label(__('project_supply_item.fields.item_kind'))
                        ->options(SupplyItemKind::class)
                        ->default($this->defaultKind()->value)
                        ->required()
                        ->native(false),
                    TextInput::make('item_code')
                        ->label(__('project_supply_item.fields.item_code'))
                        ->helperText(__('project_supply_item.help.item_code'))
                        ->maxLength(64),
                    TextInput::make('name')
                        ->label(__('project_supply_item.fields.name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('specification')
                        ->label(__('project_supply_item.fields.specification'))
                        ->columnSpanFull(),
                    Select::make('workstream_id')
                        ->label(__('project_supply_item.fields.workstream'))
                        ->relationship(
                            'workstream',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                        ->getOptionLabelFromRecordUsing(fn (ProjectWorkstream $record): string => $record->group->localizedName())
                        ->searchable()
                        ->preload()
                        ->native(false),
                    Select::make('wbs_node_id')
                        ->label(__('project_supply_item.fields.wbs_node'))
                        ->relationship(
                            'wbsNode',
                            'id',
                            modifyQueryUsing: fn ($query) => $query->where('project_id', $this->getOwnerRecord()->getKey()),
                        )
                        ->getOptionLabelFromRecordUsing(fn (WbsNode $record): string => $record->wbs_code.' · '.$record->name)
                        ->searchable()
                        ->preload()
                        ->native(false),
                ])),
            Section::make(__('project_supply_item.sections.commercial'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('quantity')
                        ->label(__('project_supply_item.fields.quantity'))
                        ->numeric()
                        ->minValue(0.0001)
                        ->default(1)
                        ->required(),
                    Select::make('uom_id')
                        ->label(__('project_supply_item.fields.uom'))
                        ->relationship('uom', 'name_tr')
                        ->searchable()
                        ->preload()
                        ->native(false),
                    TextInput::make('unit_cost')
                        ->label(__('project_supply_item.fields.unit_cost'))
                        ->numeric()
                        ->minValue(0),
                    Select::make('currency_code')
                        ->label(__('project_supply_item.fields.currency'))
                        ->helperText(__('project_supply_item.help.currency'))
                        ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                        ->default(fn (): ?string => $this->getOwnerRecord()->currency_code)
                        ->searchable()
                        ->native(false),
                    Select::make('supplier_party_id')
                        ->label(__('project_supply_item.fields.supplier'))
                        ->relationship('supplier', 'display_name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->columnSpan(2),
                ])),
            Section::make(__('project_supply_item.sections.dates'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('status')
                        ->label(__('project_supply_item.fields.status'))
                        ->options(SupplyItemStatus::class)
                        ->default(SupplyItemStatus::Planned->value)
                        ->required()
                        ->native(false),
                    DatePicker::make('needed_on')
                        ->label(__('project_supply_item.fields.needed_on'))
                        ->displayFormat('d.m.Y'),
                    DatePicker::make('expected_delivery_on')
                        ->label(__('project_supply_item.fields.expected_delivery_on'))
                        ->displayFormat('d.m.Y'),
                    DatePicker::make('ordered_on')
                        ->label(__('project_supply_item.fields.ordered_on'))
                        ->displayFormat('d.m.Y'),
                    DatePicker::make('delivered_on')
                        ->label(__('project_supply_item.fields.delivered_on'))
                        ->displayFormat('d.m.Y'),
                    Textarea::make('note')
                        ->label(__('project_supply_item.fields.note'))
                        ->columnSpanFull(),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('project_supply_item.label'))
            ->heading($this->heading())
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->scopeQuery($query))
            ->columns([
                TextColumn::make('item_kind')
                    ->label(__('project_supply_item.fields.item_kind'))
                    ->badge(),
                TextColumn::make('item_code')
                    ->label(__('project_supply_item.fields.item_code'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('project_supply_item.fields.name'))
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label(__('project_supply_item.fields.quantity'))
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn (ProjectSupplyItem $record): string => $record->uom?->symbol ? ' '.$record->uom->symbol : ''),
                TextColumn::make('unit_cost')
                    ->label(__('project_supply_item.fields.unit_cost'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('supplier.display_name')
                    ->label(__('project_supply_item.fields.supplier'))
                    ->limit(25)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('project_supply_item.fields.status'))
                    ->badge(),
                TextColumn::make('needed_on')
                    ->label(__('project_supply_item.fields.needed_on'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('expected_delivery_on')
                    ->label(__('project_supply_item.fields.expected_delivery_on'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('delivered_on')
                    ->label(__('project_supply_item.fields.delivered_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('project_supply_item.fields.status'))
                    ->options(SupplyItemStatus::class),
                SelectFilter::make('item_kind')
                    ->label(__('project_supply_item.fields.item_kind'))
                    ->options(SupplyItemKind::class),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['project_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(ProjectSupplyItemService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProjectSupplyItem $record, array $data): Model {
                        try {
                            return app(ProjectSupplyItemService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                ActionGroup::make($this->statusActions())
                    ->label(__('project_supply_item.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading(__('project_supply_item.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedShoppingCart);
    }

    /**
     * @return list<Action>
     */
    private function statusActions(): array
    {
        $actions = [];

        foreach (SupplyItemStatus::cases() as $target) {
            $actions[] = Action::make('status_'.$target->value)
                ->label(__('project_supply_item.actions.set_status', ['status' => $target->getLabel()]))
                ->color($target->getColor())
                ->requiresConfirmation()
                ->visible(fn (ProjectSupplyItem $record): bool => Gate::allows('update', $record) && $record->status !== $target)
                ->action(function (ProjectSupplyItem $record) use ($target): void {
                    try {
                        app(ProjectSupplyItemService::class)->changeStatus($record, $target);
                        DomainNotifications::success(__('project_supply_item.messages.status_changed'));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                });
        }

        return $actions;
    }
}
