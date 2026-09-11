<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffVersions\Pages;

use App\Filament\Resources\OperationHandoffVersions\OperationHandoffVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListOperationHandoffVersions extends ListRecords
{
    protected static string $resource = OperationHandoffVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
