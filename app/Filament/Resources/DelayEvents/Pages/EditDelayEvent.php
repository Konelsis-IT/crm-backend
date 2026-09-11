<?php

declare(strict_types=1);

namespace App\Filament\Resources\DelayEvents\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\DelayEvents\DelayEventResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\DelayEventService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditDelayEvent extends EditRecord
{
    protected static string $resource = DelayEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(DelayEventService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
