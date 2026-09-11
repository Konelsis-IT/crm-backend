<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\Pages;

use App\Filament\Resources\Personnel\Actions\PersonnelStatusActions;
use App\Filament\Resources\Personnel\PersonnelResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPersonnelRecord extends ViewRecord
{
    protected static string $resource = PersonnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PersonnelStatusActions::headerGroup(),
            EditAction::make(),
        ];
    }
}
