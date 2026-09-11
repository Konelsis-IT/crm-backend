<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transmittals\Pages;

use App\Filament\Resources\Transmittals\TransmittalResource;
use App\Services\Document\TransmittalService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransmittal extends CreateRecord
{
    protected static string $resource = TransmittalResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TransmittalService::class)->create($data);
    }
}
