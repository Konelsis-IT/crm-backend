<?php

declare(strict_types=1);

namespace App\Filament\Resources\GeneralExpenses\Pages;

use App\Filament\Resources\CatalogItems\Pages\ViewCatalogItem;
use App\Filament\Resources\GeneralExpenses\GeneralExpenseResource;

/** ViewCatalogItem (D-228): ayni sayfa, GeneralExpenseResource ekraninda. */
class ViewGeneralExpense extends ViewCatalogItem
{
    protected static string $resource = GeneralExpenseResource::class;
}
