<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkItems\Pages;

use App\Filament\Exports\WorkItemExporter;
use App\Filament\Resources\WorkItems\WorkItemActions;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Support\ExportActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Raporlar > Isler (B36, D-115): butun kartlarin tablosu. */
class ListWorkItems extends ListRecords
{
    protected static string $resource = WorkItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            WorkItemActions::openBoard(),
            ExportActions::table(WorkItemExporter::class),
            CreateAction::make()->label(__('work_item.actions.create')),
        ];
    }
}
