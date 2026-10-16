<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\ShowsLegacySupplyItems;

/** Yazilim tablosunun altindaki eski yazilim / otomasyon kalemleri, salt okunur (D-196). */
class LegacySoftwareItemsRelationManager extends SoftwareItemsRelationManager
{
    use ShowsLegacySupplyItems;
}
