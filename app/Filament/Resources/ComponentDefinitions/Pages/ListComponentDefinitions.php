<?php

declare(strict_types=1);

namespace App\Filament\Resources\ComponentDefinitions\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use Illuminate\Validation\ValidationException;
use App\Filament\Resources\ComponentDefinitions\ComponentDefinitionResource;
use App\Services\Project\ComponentDefinitionService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListComponentDefinitions extends ListRecords
{
    protected static string $resource = ComponentDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(ComponentDefinitionService::class)->create($data);
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.code' => __('component_definition.validation.code_taken'),
                        ]);
                    }
                }),
        ];
    }
}
