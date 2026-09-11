<?php

declare(strict_types=1);

namespace App\Filament\Resources\Contracts;

use App\Enums\Acquisition\ContractStatus;
use App\Enums\Acquisition\ContractType;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Resources\Contracts\Pages\ViewContract;
use App\Filament\Resources\Contracts\RelationManagers\VersionsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\Contract;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'contract_no';

    public static function getModelLabel(): string
    {
        return __('contract.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('contract.plural');
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
            Section::make(__('contract.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('business_case_id')
                            ->label(__('contract.fields.business_case'))
                            ->relationship('businessCase', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabledOn('edit')
                            ->dehydratedWhenHidden(false),
                        Select::make('contract_type')
                            ->label(__('contract.fields.contract_type'))
                            ->options(ContractType::class)
                            ->default(ContractType::Contract->value)
                            ->required()
                            ->native(false),
                        Select::make('customer_party_id')
                            ->label(__('contract.fields.customer_party'))
                            ->relationship('customerParty', 'display_name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        DatePicker::make('signed_on')
                            ->label(__('contract.fields.signed_on'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('effective_from')
                            ->label(__('contract.fields.effective_from'))
                            ->displayFormat('d.m.Y'),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract_no')
                    ->label(__('contract.fields.contract_no'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('businessCase.title')
                    ->label(__('contract.fields.business_case'))
                    ->limit(30),
                TextColumn::make('contract_type')
                    ->label(__('contract.fields.contract_type'))
                    ->badge(),
                TextColumn::make('customerParty.display_name')
                    ->label(__('contract.fields.customer_party')),
                TextColumn::make('status')
                    ->label(__('contract.fields.status'))
                    ->badge(),
                TextColumn::make('currentVersion.version_no')
                    ->label(__('contract.fields.current_version'))
                    ->placeholder('-'),
                TextColumn::make('signed_on')
                    ->label(__('contract.fields.signed_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('contract.fields.status'))
                    ->options(ContractStatus::class),
                SelectFilter::make('contract_type')
                    ->label(__('contract.fields.contract_type'))
                    ->options(ContractType::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('contract_no', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContracts::route('/'),
            'create' => CreateContract::route('/create'),
            'view' => ViewContract::route('/{record}'),
            'edit' => EditContract::route('/{record}/edit'),
        ];
    }
}
