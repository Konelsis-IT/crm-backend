<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Resources\WorkRequests\WorkRequestResource;
use App\Filament\Support\DomainNotifications;
use App\Services\WorkRequest\WorkRequestService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Talep eden, talep henuz kabul edilmemisken duzenler (WorkRequestPolicy::update). */
class EditWorkRequest extends EditRecord
{
    protected static string $resource = WorkRequestResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(WorkRequestService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw ValidationException::withMessages(['title' => $exception->userMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return WorkRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
