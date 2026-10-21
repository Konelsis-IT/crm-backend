<?php

declare(strict_types=1);

namespace App\Filament\Resources\GeneralExpenses\Pages;

use App\Filament\Resources\CatalogItems\Pages\ListCatalogItems;
use App\Filament\Resources\GeneralExpenses\GeneralExpenseResource;

/** ListCatalogItems (D-228): ayni sayfa, GeneralExpenseResource ekraninda. */
class ListGeneralExpenses extends ListCatalogItems
{
    protected static string $resource = GeneralExpenseResource::class;
}
