<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContractVersions;

use App\Filament\NavigationGroup;
use App\Filament\Resources\ContractVersions\Pages\EditContractVersion;
use App\Filament\Resources\ContractVersions\Pages\ListContractVersions;
use App\Filament\Resources\ContractVersions\Pages\ViewContractVersion;
use App\Filament\Resources\ContractVersions\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\ContractVersions\RelationManagers\MilestonesRelationManager;
use App\Filament\Resources\ContractVersions\RelationManagers\ObligationsRelationManager;
use App\Filament\Resources\ContractVersions\RelationManagers\PartiesRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ContractVersion;
use App\Query\Reference\ReferenceOptions;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ContractVersionResource extends Resource
{
    protected static ?string $model = ContractVersion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 51;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'version_no';

    public static function getModelLabel(): string
    {
        return __('contract_version.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contract_version.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('acquisition.admin_ui')
            && SchemaReadiness::hasBatch('B16')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract.contract_no')
                    ->label(__('contract_version.fields.contract')),
                TextColumn::make('version_no')
                    ->label(__('contract_version.fields.version_no')),
                TextColumn::make('status')
                    ->label(__('contract_version.fields.status'))
                    ->badge(),
                TextColumn::make('contract_value')
                    ->label(__('contract_version.fields.contract_value'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('currency_code')
                    ->label(__('contract_version.fields.currency')),
                TextColumn::make('effective_from')
                    ->label(__('contract_version.fields.effective_from'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
                TextColumn::make('executed_at')
                    ->label(__('contract_version.fields.executed_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            PartiesRelationManager::class,
            DocumentsRelationManager::class,
            ObligationsRelationManager::class,
            MilestonesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractVersions::route('/'),
            'view' => ViewContractVersion::route('/{record}'),
            'edit' => EditContractVersion::route('/{record}/edit'),
        ];
    }
}
