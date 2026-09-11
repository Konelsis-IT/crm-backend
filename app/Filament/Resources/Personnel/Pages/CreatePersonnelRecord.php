<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\Pages;

use App\Exceptions\Personnel\EmailAlreadyInUseException;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Services\Personnel\PersonnelService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePersonnelRecord extends CreateRecord
{
    protected static string $resource = PersonnelResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PersonnelService::class)->create($data);
        } catch (EmailAlreadyInUseException) {
            throw ValidationException::withMessages([
                'data.email' => __('personnel.validation.email_taken'),
            ]);
        }
    }
}
