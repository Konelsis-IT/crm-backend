<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNoticeVersions\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\TenderNoticeVersions\TenderNoticeVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Acquisition\TenderNoticeVersionService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditTenderNoticeVersion extends EditRecord
{
    protected static string $resource = TenderNoticeVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(TenderNoticeVersionService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
