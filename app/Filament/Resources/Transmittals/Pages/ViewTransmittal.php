<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transmittals\Pages;

use App\Filament\Resources\Transmittals\TransmittalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTransmittal extends ViewRecord
{
    protected static string $resource = TransmittalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
