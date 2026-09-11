<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transmittals\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\Transmittals\TransmittalResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Document\TransmittalService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditTransmittal extends EditRecord
{
    protected static string $resource = TransmittalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(TransmittalService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
