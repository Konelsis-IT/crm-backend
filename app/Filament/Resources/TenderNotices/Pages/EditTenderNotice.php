<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNotices\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Acquisition\TenderNoticeService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditTenderNotice extends EditRecord
{
    protected static string $resource = TenderNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(TenderNoticeService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
