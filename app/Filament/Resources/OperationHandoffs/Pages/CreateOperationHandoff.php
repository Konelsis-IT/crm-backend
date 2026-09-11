<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffs\Pages;

use App\Exceptions\DuplicateRecordException;
use Illuminate\Validation\ValidationException;
use App\Filament\Resources\OperationHandoffs\OperationHandoffResource;
use App\Services\Acquisition\OperationHandoffService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOperationHandoff extends CreateRecord
{
    protected static string $resource = OperationHandoffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(OperationHandoffService::class)->create($data);
        } catch (DuplicateRecordException) {
            throw ValidationException::withMessages([
                'data.business_case_id' => __('operation_handoff.validation.duplicate'),
            ]);
        }
    }
}
