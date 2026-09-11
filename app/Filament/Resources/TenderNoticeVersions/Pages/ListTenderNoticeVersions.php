<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNoticeVersions\Pages;

use App\Filament\Resources\TenderNoticeVersions\TenderNoticeVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListTenderNoticeVersions extends ListRecords
{
    protected static string $resource = TenderNoticeVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
