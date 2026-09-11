<?php

declare(strict_types=1);

namespace App\Filament\Resources\StageNodes\Pages;

use App\Filament\Resources\StageNodes\StageNodeResource;
use Filament\Resources\Pages\ListRecords;

class ListStageNodes extends ListRecords
{
    protected static string $resource = StageNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
