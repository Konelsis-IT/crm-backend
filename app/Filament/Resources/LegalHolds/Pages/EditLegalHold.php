<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalHolds\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\LegalHolds\LegalHoldResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Document\LegalHoldService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditLegalHold extends EditRecord
{
    protected static string $resource = LegalHoldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(LegalHoldService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
