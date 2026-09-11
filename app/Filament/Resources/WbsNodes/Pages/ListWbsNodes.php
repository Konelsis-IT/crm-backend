<?php

declare(strict_types=1);

namespace App\Filament\Resources\WbsNodes\Pages;

use App\Filament\Resources\WbsNodes\WbsNodeResource;
use Filament\Resources\Pages\ListRecords;

class ListWbsNodes extends ListRecords
{
    protected static string $resource = WbsNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
