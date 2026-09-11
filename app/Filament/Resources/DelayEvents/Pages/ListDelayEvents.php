<?php

declare(strict_types=1);

namespace App\Filament\Resources\DelayEvents\Pages;

use App\Filament\Resources\DelayEvents\DelayEventResource;
use Filament\Resources\Pages\ListRecords;

class ListDelayEvents extends ListRecords
{
    protected static string $resource = DelayEventResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
