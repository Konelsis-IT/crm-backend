<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringWorks\Pages;

use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\RecurringWorks\RecurringWorkResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Report\RecurringWorkService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/** Yeni gorev ve sorumluluk: kayit RecurringWorkService'ten gecer; bu donemin isi hemen acilir. */
class CreateRecurringWork extends CreateRecord
{
    use HasColoredFormActions;

    protected static string $resource = RecurringWorkResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RecurringWorkService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
