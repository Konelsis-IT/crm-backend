<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectReferences;

use App\Enums\Platform\Feature;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\ProjectReferences\Pages\ListProjectReferences;
use App\Filament\Support\ReferenceTable;
use App\Models\Acquisition\ProjectReference;
use App\Services\Platform\FeatureFlags;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Referanslar (B50, D-177; 8 Ekim 2026 kullanici talimati: "referans eklemek
 * icin yeni Referans resource'u olustur; proje tipi de secilebilmeli").
 *
 * D-183 (9 Ekim 2026 kullanici talimati: "Referanslar kismini Ayarlar'a al ve
 * proje tipine gore sekmelere ayir"): Ayarlar > Referanslar (Ayarlar kumesinin
 * ust sekmeleri). Tek liste sayfasi: proje tipi sekmeleri (ListProjectReferences),
 * ekleme ve duzenleme pencerede, satira tiklamak ayrinti penceresi (D-125),
 * silme yok arsiv var (D-156). Tablo teklifteki referans penceresiyle ortaktir
 * (ReferenceTable). Erisim `acquisition.references` ozelligine ve B50'ye bagli.
 */
class ProjectReferenceResource extends Resource
{
    protected static ?string $model = ProjectReference::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 90;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'references';

    public static function getModelLabel(): string
    {
        return __('project_reference.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('project_reference.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::References) && ReferenceTable::enabled() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(ReferenceTable::formComponents());
    }

    public static function table(Table $table): Table
    {
        // D-183: proje tipi suzgeci yerine sekmeler (ListProjectReferences::getTabs).
        return app(ReferenceTable::class)->configure($table, typeFilter: false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectReferences::route('/'),
        ];
    }
}
