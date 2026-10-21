<?php

declare(strict_types=1);

namespace App\Filament\Resources\GeneralExpenses\Pages;

use App\Filament\Resources\CatalogCategories\Pages\ManageCatalogCategories;
use App\Filament\Resources\GeneralExpenses\GeneralExpenseCategoryResource;

/** ManageCatalogCategories (D-228 ek): ayni sayfa, GeneralExpenseCategoryResource ekraninda. */
class ManageGeneralExpenseCategories extends ManageCatalogCategories
{
    protected static string $resource = GeneralExpenseCategoryResource::class;
}
