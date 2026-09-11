<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepartmentHandoffVersions\Pages;

use App\Filament\Resources\DepartmentHandoffVersions\DepartmentHandoffVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListDepartmentHandoffVersions extends ListRecords
{
    protected static string $resource = DepartmentHandoffVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
