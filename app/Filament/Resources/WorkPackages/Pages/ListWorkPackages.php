<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkPackages\Pages;

use App\Filament\Resources\WorkPackages\WorkPackageResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkPackages extends ListRecords
{
    protected static string $resource = WorkPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
