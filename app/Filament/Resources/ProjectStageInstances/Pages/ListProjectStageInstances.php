<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectStageInstances\Pages;

use App\Filament\Resources\ProjectStageInstances\ProjectStageInstanceResource;
use Filament\Resources\Pages\ListRecords;

class ListProjectStageInstances extends ListRecords
{
    protected static string $resource = ProjectStageInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
