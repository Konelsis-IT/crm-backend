<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalHolds\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use App\Filament\Resources\LegalHolds\LegalHoldResource;
use App\Services\Document\LegalHoldService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateLegalHold extends CreateRecord
{
    protected static string $resource = LegalHoldResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(LegalHoldService::class)->create($data);
        } catch (CodeAlreadyInUseException) {
            throw ValidationException::withMessages([
                'data.code' => __('legal_hold.validation.code_taken'),
            ]);
        }
    }
}
