<?php

declare(strict_types=1);

namespace App\Filament\Resources\Positions\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use App\Filament\Resources\Positions\PositionResource;
use App\Services\Personnel\PositionService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListPositions extends ListRecords
{
    protected static string $resource = PositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(PositionService::class)->create($data);
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.code' => __('position.validation.code_taken'),
                        ]);
                    }
                }),
        ];
    }
}
