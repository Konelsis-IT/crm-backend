<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectSupplyItems;

use App\Enums\Project\SupplyItemKind;
use App\Enums\Project\SupplyItemStatus;
use App\Exceptions\AbstractException;
use App\Filament\Clusters\Procurement;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\ProjectSupplyItems\Pages\CreateProjectSupplyItem;
use App\Filament\Resources\ProjectSupplyItems\Pages\EditProjectSupplyItem;
use App\Filament\Resources\ProjectSupplyItems\Pages\ListProjectSupplyItems;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\ProjectSupplyItem;
use App\Query\Project\ProjectCatalogQueries;
use App\Query\Reference\ReferenceOptions;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\ProjectSupplyItemService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Satin alma masasi: tum projelerin tedarik kalemleri tek listede
 * (departmanin kendi kuyrugu). Ayni kayitlar proje calisma alaninin
 * Satin Alma / Lojistik / Yazilim sekmelerinde de gorunur.
 */
class ProjectSupplyItemResource extends Resource
{
    protected static ?string $model = ProjectSupplyItem::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $cluster = Procurement::class;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('project_supply_item.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('project_supply_item.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('projects.admin_ui')
            && SchemaReadiness::hasBatch('B17A')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('project_supply_item.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('project_id')
                        ->label(__('project_supply_item.fields.project'))
                        ->options(fn (): array => app(ProjectCatalogQueries::class)->projectOptions())
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->dehydratedWhenHidden(false)
                        ->native(false)
                        ->columnSpanFull(),
                    Select::make('item_kind')
                        ->label(__('project_supply_item.fields.item_kind'))
                        ->options(SupplyItemKind::class)
                        ->default(SupplyItemKind::Product->value)
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
                        ->options(fn (Get $get): array => app(ProjectCatalogQueries::class)->workstreamOptions((int) ($get('project_id') ?? 0)))
                        ->searchable()
                        ->native(false),
                    Select::make('wbs_node_id')
                        ->label(__('project_supply_item.fields.wbs_node'))
                        ->options(fn (Get $get): array => app(ProjectCatalogQueries::class)->wbsOptions((int) ($get('project_id') ?? 0)))
                        ->searchable()
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.businessCode.formatted_code')
                    ->label(__('project.fields.business_code'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('project.name')
                    ->label(__('project_supply_item.fields.project'))
                    ->limit(30)
                    ->searchable()
                    ->url(fn (ProjectSupplyItem $record): ?string => $record->project === null ? null : ProjectResource::getUrl('view', ['record' => $record->project])),
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
                TextColumn::make('status')
                    ->label(__('project_supply_item.fields.status'))
                    ->badge(),
                TextColumn::make('supplier.display_name')
                    ->label(__('project_supply_item.fields.supplier'))
                    ->limit(25)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('needed_on')
                    ->label(__('project_supply_item.fields.needed_on'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('delivered_on')
                    ->label(__('project_supply_item.fields.delivered_on'))
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->label(__('project_supply_item.fields.project'))
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label(__('project_supply_item.fields.status'))
                    ->options(SupplyItemStatus::class),
                SelectFilter::make('item_kind')
                    ->label(__('project_supply_item.fields.item_kind'))
                    ->options(SupplyItemKind::class),
                Filter::make('my_step')
                    ->label(__('project_supply_item.filters.my_step'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'project.primaryFocusWorkstream.group',
                        fn (Builder $group): Builder => $group->where('code', 'PROCUREMENT'),
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make(self::statusActions())
                    ->label(__('project_supply_item.actions.change_status'))
                    ->icon(Heroicon::OutlinedArrowPath),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectSupplyItems::route('/'),
            'create' => CreateProjectSupplyItem::route('/create'),
            'edit' => EditProjectSupplyItem::route('/{record}/edit'),
        ];
    }

    /**
     * @return list<Action>
     */
    private static function statusActions(): array
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
