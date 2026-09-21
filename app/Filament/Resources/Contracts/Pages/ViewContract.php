<?php

declare(strict_types=1);

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Exports\ContractExporter;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\ExportActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ExportActions::record(ContractExporter::class),
        ];
    }
}
