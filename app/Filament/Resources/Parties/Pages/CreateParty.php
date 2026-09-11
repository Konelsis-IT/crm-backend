<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\Pages;

use App\Filament\Resources\Parties\PartyResource;
use App\Services\Party\PartyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateParty extends CreateRecord
{
    protected static string $resource = PartyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PartyService::class)->create($data);
    }
}
