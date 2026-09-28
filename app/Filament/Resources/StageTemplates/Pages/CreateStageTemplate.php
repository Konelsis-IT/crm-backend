<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplates\Pages;

use App\Filament\Resources\StageTemplates\StageTemplateResource;
use App\Services\Project\StageTemplateService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStageTemplate extends CreateRecord
{
    protected static string $resource = StageTemplateResource::class;

    /** Kod arayuzde girilmez (D-130); servis Turkce addan benzersiz uretir. */
    protected function handleRecordCreation(array $data): Model
    {
        return app(StageTemplateService::class)->create($data);
    }
}
