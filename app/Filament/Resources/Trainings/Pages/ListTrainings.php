<?php

declare(strict_types=1);

namespace App\Filament\Resources\Trainings\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use App\Filament\Resources\Trainings\TrainingResource;
use App\Services\Personnel\TrainingService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListTrainings extends ListRecords
{
    protected static string $resource = TrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(TrainingService::class)->create($data);
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.code' => __('training.validation.code_taken'),
                        ]);
                    }
                }),
        ];
    }
}
