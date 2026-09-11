<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProposalVersions;

use App\Filament\NavigationGroup;
use App\Filament\Resources\ProposalVersions\Pages\EditProposalVersion;
use App\Filament\Resources\ProposalVersions\Pages\ListProposalVersions;
use App\Filament\Resources\ProposalVersions\Pages\ViewProposalVersion;
use App\Filament\Resources\ProposalVersions\RelationManagers\BrandItemsRelationManager;
use App\Filament\Resources\ProposalVersions\RelationManagers\ComplianceItemsRelationManager;
use App\Filament\Resources\ProposalVersions\RelationManagers\DeviationsRelationManager;
use App\Filament\Resources\ProposalVersions\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\ProposalVersions\RelationManagers\EstimateVersionsRelationManager;
use App\Filament\Resources\ProposalVersions\RelationManagers\ResponsibilityItemsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\ProposalVersion;
use App\Query\Document\DocumentQueries;
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
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProposalVersionResource extends Resource
{
    protected static ?string $model = ProposalVersion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 41;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'version_no';

    public static function getModelLabel(): string
    {
        return __('proposal_version.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('proposal_version.plural');
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
                        Select::make('project_group_opinion_document_revision_id')
                            ->label(__('proposal_version.fields.project_group_opinion_document_revision'))
                            ->options(fn (): array => app(DocumentQueries::class)->revisionOptions())
                            ->searchable()
                            ->native(false),
                        Textarea::make('summary')
                            ->label(__('proposal_version.fields.summary'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('proposal.proposal_no')
                    ->label(__('proposal_version.fields.proposal')),
                TextColumn::make('version_no')
                    ->label(__('proposal_version.fields.version_no')),
                TextColumn::make('status')
                    ->label(__('proposal_version.fields.status'))
                    ->badge(),
                TextColumn::make('currency_code')
                    ->label(__('proposal_version.fields.currency')),
                TextColumn::make('total_price')
                    ->label(__('proposal_version.fields.total_price'))
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('margin_pct')
                    ->label(__('proposal_version.fields.margin_pct'))
                    ->suffix('%')
                    ->placeholder('-'),
                TextColumn::make('preparer.full_name')
                    ->label(__('proposal_version.fields.preparer')),
                TextColumn::make('approved_at')
                    ->label(__('proposal_version.fields.approved_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextColumn::make('submitted_at')
                    ->label(__('proposal_version.fields.submitted_at'))
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
            DocumentsRelationManager::class,
            ComplianceItemsRelationManager::class,
            DeviationsRelationManager::class,
            BrandItemsRelationManager::class,
            ResponsibilityItemsRelationManager::class,
            EstimateVersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProposalVersions::route('/'),
            'view' => ViewProposalVersion::route('/{record}'),
            'edit' => EditProposalVersion::route('/{record}/edit'),
        ];
    }
}
