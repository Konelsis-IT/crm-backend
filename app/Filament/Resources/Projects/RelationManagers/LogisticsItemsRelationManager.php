<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\Project\SupplyItemStatus;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lojistik adimi: siparisi verilmis kalemlerin sevkiyat ve teslim takibi
 * (planlanan/talep edilen kalemler satin alma sekmesinde kalir).
 */
class LogisticsItemsRelationManager extends SupplyItemsRelationManager
{
    protected static string | BackedEnum | null $icon = Heroicon::OutlinedTruck;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('project_supply_item.relation.logistics_title');
    }

    protected function scopeQuery(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (SupplyItemStatus $status): string => $status->value,
            SupplyItemStatus::orderedStates(),
        ));
    }

    protected function heading(): string
    {
        return __('project_supply_item.relation.logistics_title');
    }
}
