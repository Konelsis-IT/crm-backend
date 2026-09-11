<?php

declare(strict_types=1);

namespace App\Filament\Resources\EstimateVersions\Pages;

use App\Filament\Resources\EstimateVersions\EstimateVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListEstimateVersions extends ListRecords
{
    protected static string $resource = EstimateVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
