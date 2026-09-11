<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNoticeVersions\Pages;

use App\Filament\Resources\TenderNoticeVersions\TenderNoticeVersionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTenderNoticeVersion extends ViewRecord
{
    protected static string $resource = TenderNoticeVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
