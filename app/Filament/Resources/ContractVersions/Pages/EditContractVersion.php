<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContractVersions\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\ContractVersions\ContractVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Acquisition\ContractVersionService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditContractVersion extends EditRecord
{
    protected static string $resource = ContractVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(ContractVersionService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
