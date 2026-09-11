<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents;

use App\Filament\Clusters\Documents;
use App\Filament\Resources\Documents\Pages\CreateDocumentRecord;
use App\Filament\Resources\Documents\Pages\EditDocumentRecord;
use App\Filament\Resources\Documents\Pages\ListDocumentRecords;
use App\Filament\Resources\Documents\Pages\ViewDocumentRecord;
use App\Filament\Resources\Documents\RelationManagers\AcknowledgementsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\ApprovalRequestsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\DistributionsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\LinksRelationManager;
use App\Filament\Resources\Documents\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Documents\RelationManagers\SharesRelationManager;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Filament\Resources\Documents\Tables\DocumentTable;
use App\Models\Document\Document;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $cluster = Documents::class;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('document.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('document.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('documents.admin_ui')
            && SchemaReadiness::hasBatch('B06')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTable::configure($table);
    }

    public static function getRelations(): array
    {
        // Paylasim ve onay listeleri kendi batch'leri (B06A / B07) uygulanmadan
        // eklenmez; detay sayfasi (ViewDocumentRecord) ayni kosulla sekme kurar.
        return [
            ...(SchemaReadiness::hasBatch('B06A') ? [SharesRelationManager::class] : []),
            ...(FeatureFlags::enabled('approvals.admin_ui') && SchemaReadiness::hasBatch('B07') ? [ApprovalRequestsRelationManager::class] : []),
            RevisionsRelationManager::class,
            LinksRelationManager::class,
            ReviewsRelationManager::class,
            DistributionsRelationManager::class,
            AcknowledgementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentRecords::route('/'),
            'create' => CreateDocumentRecord::route('/create'),
            'view' => ViewDocumentRecord::route('/{record}'),
            'edit' => EditDocumentRecord::route('/{record}/edit'),
        ];
    }
}
