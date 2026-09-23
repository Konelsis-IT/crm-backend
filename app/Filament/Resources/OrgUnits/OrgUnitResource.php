<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrgUnits;

use App\Enums\Personnel\OrgUnitStatus;
use App\Enums\Personnel\OrgUnitType;
use App\Exceptions\Personnel\SelfParentNotAllowedException;
use App\Exceptions\StaleRecordException;
use App\Filament\NavigationGroup;
use App\Filament\Resources\OrgUnits\Pages\ListOrgUnits;
use App\Filament\Resources\OrgUnits\Pages\ViewOrgUnit;
use App\Filament\Resources\OrgUnits\RelationManagers\UnitPersonnelRelationManager;
use App\Filament\Resources\OrgUnits\Schemas\OrgUnitInfolist;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\OrgUnit;
use App\Query\Personnel\OrganizationQueries;
use App\Services\Personnel\OrgUnitService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OrgUnitResource extends Resource
{
    protected static ?string $model = OrgUnit::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Administrative;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('org_unit.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('org_unit.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('personnel.admin_ui')
            && SchemaReadiness::hasBatch('B03')
            && parent::canAccess();
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrgUnitInfolist::make($schema);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('org_unit.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    TextInput::make('name')
                        ->label(__('org_unit.fields.name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('unit_type')
                        ->label(__('org_unit.fields.unit_type'))
                        ->options(OrgUnitType::class)
                        ->default(OrgUnitType::Department->value)
                        ->required()
                        ->native(false),
                    Select::make('parent_org_unit_id')
                        ->label(__('org_unit.fields.parent'))
                        ->helperText(__('org_unit.help.parent'))
                        ->options(fn (?OrgUnit $record): array => app(OrganizationQueries::class)
                            ->orgUnitOptions($record !== null ? (int) $record->getKey() : null))
                        ->searchable()
                        ->native(false)
                        ->afterStateHydrated(function (Select $component, ?OrgUnit $record): void {
                            $component->state($record?->currentParent()?->getKey());
                        }),
                    Select::make('manager_personnel_id')
                        ->label(__('org_unit.fields.manager'))
                        ->relationship('manager', 'full_name')
                        ->searchable()
                        ->preload()
                        ->native(false),
                    TextInput::make('cost_center_code')
                        ->label(__('org_unit.fields.cost_center_code'))
                        ->maxLength(32),
                    Select::make('status')
                        ->label(__('org_unit.fields.status'))
                        ->options(OrgUnitStatus::class)
                        ->default(OrgUnitStatus::Active->value)
                        ->required()
                        ->native(false),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('org_unit.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('unit_type')
                    ->label(__('org_unit.fields.unit_type'))
                    ->badge(),
                TextColumn::make('manager.full_name')
                    ->label(__('org_unit.fields.manager'))
                    ->placeholder('-'),
                TextColumn::make('personnel_count')
                    ->label(__('org_unit.fields.personnel_count'))
                    ->counts('personnel'),
                TextColumn::make('status')
                    ->label(__('org_unit.fields.status'))
                    ->badge(),
            ])
            ->recordUrl(fn (OrgUnit $record): string => ViewOrgUnit::getUrl(['record' => $record]))
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->using(function (OrgUnit $record, array $data): Model {
                        try {
                            return app(OrgUnitService::class)->update($record, $data);
                        } catch (StaleRecordException | SelfParentNotAllowedException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('name');
    }

    /** Birime bagli personel (D-116). */
    public static function getRelations(): array
    {
        return [
            UnitPersonnelRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrgUnits::route('/'),
            'view' => ViewOrgUnit::route('/{record}'),
        ];
    }
}
