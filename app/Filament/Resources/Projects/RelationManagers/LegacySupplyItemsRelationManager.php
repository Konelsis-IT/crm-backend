<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\RelationManagers\Concerns\ShowsLegacySupplyItems;

/** Satin alma tablosunun altindaki eski tedarik kalemleri, salt okunur (D-196). */
class LegacySupplyItemsRelationManager extends SupplyItemsRelationManager
{
    use ShowsLegacySupplyItems;
}
