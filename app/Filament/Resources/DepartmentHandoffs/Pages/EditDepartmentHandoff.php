<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepartmentHandoffs\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\DepartmentHandoffs\DepartmentHandoffResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\DepartmentHandoffService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditDepartmentHandoff extends EditRecord
{
    protected static string $resource = DepartmentHandoffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(DepartmentHandoffService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
