<?php

declare(strict_types=1);

namespace App\Filament\Resources\EstimateVersions;

use App\Filament\NavigationGroup;
use App\Filament\Resources\EstimateVersions\Pages\EditEstimateVersion;
use App\Filament\Resources\EstimateVersions\Pages\ListEstimateVersions;
use App\Filament\Resources\EstimateVersions\Pages\ViewEstimateVersion;
use App\Filament\Resources\EstimateVersions\RelationManagers\BoqItemsRelationManager;
use App\Filament\Resources\EstimateVersions\RelationManagers\LinesRelationManager;
use App\Filament\Resources\EstimateVersions\RelationManagers\ScenariosRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\EstimateVersion;
use App\Query\Reference\ReferenceOptions;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
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

class EstimateVersionResource extends Resource
{
    protected static ?string $model = EstimateVersion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 42;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'version_no';

    public static function getModelLabel(): string
    {
        return __('estimate_version.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('estimate_version.plural');
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
            Section::make(__('estimate_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('currency_code')
                            ->label(__('estimate_version.fields.currency'))
                            ->options(fn (): array => app(ReferenceOptions::class)->currencies())
                            ->searchable()
                            ->required()
                            ->native(false),
                        TextInput::make('target_margin_pct')
                            ->label(__('estimate_version.fields.target_margin_pct'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100),
                        KeyValue::make('exchange_rate_snapshot')
                            ->label(__('estimate_version.fields.exchange_rate_snapshot'))
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label(__('estimate_version.fields.notes'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('proposalVersion.proposal.proposal_no')
                    ->label(__('estimate_version.fields.proposal')),
                TextColumn::make('proposalVersion.version_no')
                    ->label(__('estimate_version.fields.proposal_version')),
                TextColumn::make('version_no')
                    ->label(__('estimate_version.fields.version_no')),
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
            LinesRelationManager::class,
            ScenariosRelationManager::class,
            BoqItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEstimateVersions::route('/'),
            'view' => ViewEstimateVersion::route('/{record}'),
            'edit' => EditEstimateVersion::route('/{record}/edit'),
        ];
    }
}
