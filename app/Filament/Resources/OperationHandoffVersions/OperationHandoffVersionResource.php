<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffVersions;

use App\Filament\NavigationGroup;
use App\Filament\Resources\OperationHandoffVersions\Pages\EditOperationHandoffVersion;
use App\Filament\Resources\OperationHandoffVersions\Pages\ListOperationHandoffVersions;
use App\Filament\Resources\OperationHandoffVersions\Pages\ViewOperationHandoffVersion;
use App\Filament\Resources\OperationHandoffVersions\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\OperationHandoffVersions\RelationManagers\ReviewsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\OperationHandoffVersion;
use App\Query\Document\DocumentQueries;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class OperationHandoffVersionResource extends Resource
{
    protected static ?string $model = OperationHandoffVersion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Acquisition;

    protected static ?int $navigationSort = 61;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'version_no';

    public static function getModelLabel(): string
    {
        return __('operation_handoff_version.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('operation_handoff_version.plural');
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
            Section::make(__('operation_handoff_version.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Select::make('manifest_document_revision_id')
                            ->label(__('operation_handoff_version.fields.manifest_document_revision'))
                            ->options(fn (): array => app(DocumentQueries::class)->revisionOptions())
                            ->searchable()
                            ->native(false),
                        Textarea::make('decision_reason')
                            ->label(__('operation_handoff_version.fields.decision_reason'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('handoff.businessCase.title')
                    ->label(__('operation_handoff_version.fields.business_case'))
                    ->limit(40),
                TextColumn::make('version_no')
                    ->label(__('operation_handoff_version.fields.version_no')),
                TextColumn::make('status')
                    ->label(__('operation_handoff_version.fields.status'))
                    ->badge(),
                TextColumn::make('proposalVersion.version_no')
                    ->label(__('operation_handoff_version.fields.proposal_version')),
                TextColumn::make('contractVersion.version_no')
                    ->label(__('operation_handoff_version.fields.contract_version'))
                    ->placeholder('-'),
                TextColumn::make('submitted_at')
                    ->label(__('operation_handoff_version.fields.submitted_at'))
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
            ItemsRelationManager::class,
            ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationHandoffVersions::route('/'),
            'view' => ViewOperationHandoffVersion::route('/{record}'),
            'edit' => EditOperationHandoffVersion::route('/{record}/edit'),
        ];
    }
}
