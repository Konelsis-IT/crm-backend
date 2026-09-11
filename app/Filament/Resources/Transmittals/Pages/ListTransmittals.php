<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transmittals\Pages;

use App\Filament\Resources\Transmittals\TransmittalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransmittals extends ListRecords
{
    protected static string $resource = TransmittalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
