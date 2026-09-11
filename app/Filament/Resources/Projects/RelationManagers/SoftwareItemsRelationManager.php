<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Project\SupplyItemKind;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Yazilim / otomasyon adimi: yalniz yazilim turundeki tedarik kalemleri. */
class SoftwareItemsRelationManager extends SupplyItemsRelationManager
{
    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCpuChip;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_supply_item.relation.software_title');
    }

    protected function scopeQuery(Builder $query): Builder
    {
        return $query->where('item_kind', SupplyItemKind::Software->value);
    }

    protected function defaultKind(): SupplyItemKind
    {
        return SupplyItemKind::Software;
    }

    protected function heading(): string
    {
        return __('project_supply_item.relation.software_title');
    }
}
