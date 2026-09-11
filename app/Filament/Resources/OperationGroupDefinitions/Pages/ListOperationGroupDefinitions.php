<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationGroupDefinitions\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use Illuminate\Validation\ValidationException;
use App\Filament\Resources\OperationGroupDefinitions\OperationGroupDefinitionResource;
use App\Services\Project\OperationGroupDefinitionService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListOperationGroupDefinitions extends ListRecords
{
    protected static string $resource = OperationGroupDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(OperationGroupDefinitionService::class)->create($data);
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.code' => __('operation_group_definition.validation.code_taken'),
                        ]);
                    }
                }),
        ];
    }
}
