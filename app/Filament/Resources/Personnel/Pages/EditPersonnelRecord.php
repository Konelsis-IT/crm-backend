<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\Pages;

use App\Exceptions\Personnel\EmailAlreadyInUseException;
use App\Exceptions\StaleRecordException;
use App\Filament\Resources\Personnel\Actions\PersonnelStatusActions;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\PersonnelFormData;
use App\Models\Personnel\Personnel;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Personnel\PersonnelService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditPersonnelRecord extends EditRecord
{
    protected static string $resource = PersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PersonnelStatusActions::headerGroup(),
            ViewAction::make(),
        ];
    }

    /** Mevcut yetkinlikleri forma yukler. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Personnel $record */
        $record = $this->getRecord();

        $data['competencyRecords'] = app(PersonnelQueries::class)->competencyRows($record);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Personnel $record */
        $data = PersonnelFormData::forEditScreen($data, $record);

        try {
            return app(PersonnelService::class)->update($record, $data);
        } catch (EmailAlreadyInUseException) {
            throw ValidationException::withMessages([
                'data.email' => __('personnel.validation.email_taken'),
            ]);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
