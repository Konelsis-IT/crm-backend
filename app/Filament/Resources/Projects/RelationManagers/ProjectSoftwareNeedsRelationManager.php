<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Query\Project\ProjectPurchasingQueries;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Proje sayfasinda Yazilim adiminin "Yazılım" tablosu (D-196; eski "Yazılım /
 * otomasyon kalemleri" tablosunun yerine): projenin Satin alinacaklar'indan
 * yazilim kurali tutanlar (ProjectPurchasingQueries::SOFTWARE_KEYWORDS:
 * kategori ya da ust kategori, kalem adi veya listede olmayan kalemin adi
 * "Yazılım", "SCADA", "otomasyon" iceriyor). Sutunlar ve "Satın alma talebi
 * aç" Satin alma tablosuyla aynidir.
 */
class ProjectSoftwareNeedsRelationManager extends ProjectPurchaseNeedsRelationManager
{
    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCpuChip;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_supply_item.purchasing.software_title');
    }

    protected function scopeNeeds(Builder $query): Builder
    {
        return app(ProjectPurchasingQueries::class)->softwareScope($query);
    }

    protected function heading(): string
    {
        return __('project_supply_item.purchasing.software_title');
    }

    protected function emptyHeading(): string
    {
        return __('project_supply_item.purchasing.software_empty');
    }
}
