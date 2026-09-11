<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNotices\Pages;

use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenderNotices extends ListRecords
{
    protected static string $resource = TenderNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
