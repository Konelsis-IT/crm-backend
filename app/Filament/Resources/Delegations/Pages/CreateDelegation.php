<?php

declare(strict_types=1);

namespace App\Filament\Resources\Delegations\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\Delegations\DelegationResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Approval\DelegationService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateDelegation extends CreateRecord
{
    protected static string $resource = DelegationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(DelegationService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
