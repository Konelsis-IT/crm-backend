<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNotices\Pages;

use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Filament\Support\DraftSupport;
use App\Models\Acquisition\TenderNotice;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListTenderNotices extends ListRecords
{
    protected static string $resource = TenderNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Tumu / Taslaklar (B43, D-155; taslak ozelligi kapaliysa sekme yok).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return DraftSupport::tabs(TenderNotice::class);
    }
}
