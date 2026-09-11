<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepartmentHandoffVersions\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\DepartmentHandoffVersions\DepartmentHandoffVersionResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\DepartmentHandoffVersionService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditDepartmentHandoffVersion extends EditRecord
{
    protected static string $resource = DepartmentHandoffVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(DepartmentHandoffVersionService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
