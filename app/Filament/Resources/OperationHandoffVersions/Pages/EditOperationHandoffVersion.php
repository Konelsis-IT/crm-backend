<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffVersions\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\OperationHandoffVersions\OperationHandoffVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Acquisition\OperationHandoffVersionService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditOperationHandoffVersion extends EditRecord
{
    protected static string $resource = OperationHandoffVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(OperationHandoffVersionService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
