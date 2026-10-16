<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringWorks\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\RecurringWorks\RecurringWorkResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Gorev ve sorumluluk detayi: tanim, uyum ve donem isleri. */
class ViewRecurringWork extends ViewRecord
{
    use HasRecordHeading;

    protected static string $resource = RecurringWorkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            RecurringWorkResource::archiveAction(),
        ];
    }
}
