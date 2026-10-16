<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Concerns\HasRecordHeading;
use App\Exceptions\StaleRecordException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Document\DocumentService;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditDocumentRecord extends EditRecord
{
    use HasRecordHeading;
    use HasColoredFormActions;

    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cancelHeaderAction(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(DocumentService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
