<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectWorkstreams\Pages;

use App\Filament\Resources\ProjectWorkstreams\ProjectWorkstreamResource;
use Filament\Resources\Pages\ListRecords;

class ListProjectWorkstreams extends ListRecords
{
    protected static string $resource = ProjectWorkstreamResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
