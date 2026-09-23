<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems\Pages;

use App\Filament\Exports\WorkItemExporter;
use App\Filament\Resources\WorkItems\WorkItemActions;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Support\ExportActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Kart detayi ve gecmisi (B36, D-115). */
class ViewWorkItem extends ViewRecord
{
    protected static string $resource = WorkItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            WorkItemActions::changeStatus(),
            WorkItemActions::toggleCritical(),
            EditAction::make(),
            WorkItemActions::openBoard(),
            WorkItemActions::delete(),
            ExportActions::record(WorkItemExporter::class),
        ];
    }
}
