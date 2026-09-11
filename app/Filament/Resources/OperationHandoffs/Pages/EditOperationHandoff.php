<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffs\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\OperationHandoffs\OperationHandoffResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Acquisition\OperationHandoffService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditOperationHandoff extends EditRecord
{
    protected static string $resource = OperationHandoffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(OperationHandoffService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
