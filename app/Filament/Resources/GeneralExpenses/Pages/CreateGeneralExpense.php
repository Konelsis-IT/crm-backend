<?php

declare(strict_types=1);

namespace App\Filament\Resources\GeneralExpenses\Pages;

use App\Filament\Resources\CatalogItems\Pages\CreateCatalogItem;
use App\Filament\Resources\GeneralExpenses\GeneralExpenseResource;

/** CreateCatalogItem (D-228): ayni sayfa, GeneralExpenseResource ekraninda. */
class CreateGeneralExpense extends CreateCatalogItem
{
    protected static string $resource = GeneralExpenseResource::class;
}
