<?php

declare(strict_types=1);

namespace App\Filament\Resources\Delegations\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\Delegations\DelegationResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Approval\DelegationService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditDelegation extends EditRecord
{
    protected static string $resource = DelegationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(DelegationService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
