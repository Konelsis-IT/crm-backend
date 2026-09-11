<?php

declare(strict_types=1);

namespace App\Filament\Resources\Competencies\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use App\Filament\Resources\Competencies\CompetencyResource;
use App\Services\Personnel\CompetencyService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListCompetencies extends ListRecords
{
    protected static string $resource = CompetencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(CompetencyService::class)->create($data);
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.code' => __('competency.validation.code_taken'),
                        ]);
                    }
                }),
        ];
    }
}
