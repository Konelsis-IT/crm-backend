<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringWorks\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Concerns\HasRecordHeading;
use App\Filament\Resources\RecurringWorks\RecurringWorkResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Report\RecurringWorkService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/** Gorev ve sorumluluk duzenleme; acilmis donem isleri degismez, sonraki donemler yeni tanimla acilir. */
class EditRecurringWork extends EditRecord
{
    use HasRecordHeading;
    use HasColoredFormActions;

    protected static string $resource = RecurringWorkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecurringWorkResource::archiveAction(),
            $this->cancelHeaderAction(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(RecurringWorkService::class)->update($record, $data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
