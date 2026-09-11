<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrgUnits\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use App\Filament\Resources\OrgUnits\OrgUnitResource;
use App\Services\Personnel\OrgUnitService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListOrgUnits extends ListRecords
{
    protected static string $resource = OrgUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(OrgUnitService::class)->create($data);
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.name' => __('org_unit.validation.code_taken'),
                        ]);
                    }
                }),
        ];
    }
}
