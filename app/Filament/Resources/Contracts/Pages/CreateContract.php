<?php

declare(strict_types=1);

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Services\Acquisition\ContractService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ContractService::class)->create($data);
    }
}
