<?php

declare(strict_types=1);

namespace App\Filament\Resources\GeneralExpenses\Pages;

use App\Filament\Resources\CatalogItems\Pages\EditCatalogItem;
use App\Filament\Resources\GeneralExpenses\GeneralExpenseResource;

/** EditCatalogItem (D-228): ayni sayfa, GeneralExpenseResource ekraninda. */
class EditGeneralExpense extends EditCatalogItem
{
    protected static string $resource = GeneralExpenseResource::class;
}
