<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepartmentHandoffs\Pages;

use App\Filament\Resources\DepartmentHandoffs\DepartmentHandoffResource;
use Filament\Resources\Pages\ListRecords;

class ListDepartmentHandoffs extends ListRecords
{
    protected static string $resource = DepartmentHandoffResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
