<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrgUnits\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use App\Filament\Resources\OrgUnits\OrgUnitResource;
use App\Filament\Resources\OrgUnits\Schemas\OrgUnitProjectSettings;
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
                        $unit = app(OrgUnitService::class)->create(OrgUnitProjectSettings::unitData($data));
                        // D-239: taahhutlerde calisma (operasyon grubu).
                        OrgUnitProjectSettings::save($unit, $data);

                        return $unit;
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.name' => __('org_unit.validation.code_taken'),
                        ]);
                    }
                }),
        ];
    }
}
