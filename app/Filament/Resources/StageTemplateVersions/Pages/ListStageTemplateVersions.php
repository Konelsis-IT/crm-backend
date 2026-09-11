<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplateVersions\Pages;

use App\Filament\Resources\StageTemplateVersions\StageTemplateVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListStageTemplateVersions extends ListRecords
{
    protected static string $resource = StageTemplateVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
