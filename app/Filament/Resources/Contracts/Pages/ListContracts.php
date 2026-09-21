<?php

declare(strict_types=1);

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Exports\ContractExporter;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\ExportActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::table(ContractExporter::class),
            CreateAction::make(),
        ];
    }
}
