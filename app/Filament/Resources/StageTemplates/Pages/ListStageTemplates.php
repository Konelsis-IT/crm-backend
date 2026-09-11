<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageTemplates\Pages;

use App\Filament\Resources\StageTemplates\StageTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStageTemplates extends ListRecords
{
    protected static string $resource = StageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
