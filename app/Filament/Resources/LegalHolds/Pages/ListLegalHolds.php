<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalHolds\Pages;

use App\Filament\Resources\LegalHolds\LegalHoldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLegalHolds extends ListRecords
{
    protected static string $resource = LegalHoldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
