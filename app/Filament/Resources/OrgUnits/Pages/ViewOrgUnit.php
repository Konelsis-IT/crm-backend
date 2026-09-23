<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrgUnits\Pages;

use App\Exceptions\Personnel\SelfParentNotAllowedException;
use App\Exceptions\StaleRecordException;
use App\Filament\Resources\OrgUnits\OrgUnitResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Personnel\OrgUnit;
use App\Services\Personnel\OrgUnitService;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class ViewOrgUnit extends ViewRecord
{
    protected static string $resource = OrgUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->using(function (OrgUnit $record, array $data): Model {
                    try {
                        return app(OrgUnitService::class)->update($record, $data);
                    } catch (StaleRecordException | SelfParentNotAllowedException $exception) {
                        DomainNotifications::failure($exception);

                        throw new Halt;
                    }
                }),
        ];
    }
}
