<?php

declare(strict_types=1);

namespace App\Filament\Resources\EstimateVersions\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\EstimateVersions\EstimateVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Acquisition\EstimateVersionService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditEstimateVersion extends EditRecord
{
    protected static string $resource = EstimateVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(EstimateVersionService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
