<?php

declare(strict_types=1);

namespace App\Filament\Resources\Contracts\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Acquisition\ContractService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ContractService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
