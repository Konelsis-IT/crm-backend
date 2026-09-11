<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectSupplyItems\Pages;

use App\Filament\Resources\ProjectSupplyItems\ProjectSupplyItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjectSupplyItems extends ListRecords
{
    protected static string $resource = ProjectSupplyItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
