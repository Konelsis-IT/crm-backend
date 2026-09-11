<?php

declare(strict_types=1);

namespace App\Filament\Resources\LegalHolds\Pages;

use App\Filament\Resources\LegalHolds\LegalHoldResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLegalHold extends ViewRecord
{
    protected static string $resource = LegalHoldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
