<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\ShowsLegacySupplyItems;

/** Lojistik tablosunun altindaki eski sevkiyat ve teslimat kayitlari, salt okunur (D-196). */
class LegacyLogisticsItemsRelationManager extends LogisticsItemsRelationManager
{
    use ShowsLegacySupplyItems;
}
