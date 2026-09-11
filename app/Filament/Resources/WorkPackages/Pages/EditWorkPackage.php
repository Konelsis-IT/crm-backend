<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkPackages\Pages;

use App\Exceptions\StaleRecordException;
use App\Filament\Resources\WorkPackages\WorkPackageResource;
use App\Filament\Support\DomainNotifications;
use App\Services\Project\WorkPackageService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditWorkPackage extends EditRecord
{
    protected static string $resource = WorkPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(WorkPackageService::class)->update($record, $data);
        } catch (StaleRecordException $exception) {
            DomainNotifications::failure($exception);

            throw new Halt;
        }
    }
}
