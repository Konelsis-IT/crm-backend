<?php

declare(strict_types=1);

namespace App\Filament\Resources\DelayEvents\Pages;

use App\Filament\Resources\DelayEvents\DelayEventResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDelayEvent extends ViewRecord
{
    protected static string $resource = DelayEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
