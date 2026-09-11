<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContractVersions\Pages;

use App\Filament\Resources\ContractVersions\ContractVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListContractVersions extends ListRecords
{
    protected static string $resource = ContractVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
