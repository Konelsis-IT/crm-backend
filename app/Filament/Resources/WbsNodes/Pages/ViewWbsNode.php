<?php

declare(strict_types=1);

namespace App\Filament\Resources\WbsNodes\Pages;

use App\Filament\Resources\WbsNodes\WbsNodeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWbsNode extends ViewRecord
{
    protected static string $resource = WbsNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
