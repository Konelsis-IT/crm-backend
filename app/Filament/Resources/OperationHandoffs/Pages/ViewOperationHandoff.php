<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffs\Pages;

use App\Filament\Resources\OperationHandoffs\OperationHandoffResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationHandoff extends ViewRecord
{
    protected static string $resource = OperationHandoffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
