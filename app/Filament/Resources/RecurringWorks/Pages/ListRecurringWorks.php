<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringWorks\Pages;

use App\Filament\Resources\RecurringWorks\RecurringWorkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Raporlar > Gorev ve sorumluluklar (D-219): tanimlar ve son donemlerin uyumu. */
class ListRecurringWorks extends ListRecords
{
    protected static string $resource = RecurringWorkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('recurring_work.actions.create')),
        ];
    }
}
