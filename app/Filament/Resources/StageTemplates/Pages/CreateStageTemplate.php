<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplates\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use Illuminate\Validation\ValidationException;
use App\Filament\Resources\StageTemplates\StageTemplateResource;
use App\Services\Project\StageTemplateService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStageTemplate extends CreateRecord
{
    protected static string $resource = StageTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(StageTemplateService::class)->create($data);
        } catch (CodeAlreadyInUseException) {
            throw ValidationException::withMessages([
                'data.code' => __('stage_template.validation.code_taken'),
            ]);
        }
    }
}
