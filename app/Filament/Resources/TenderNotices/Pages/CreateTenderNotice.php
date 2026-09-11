<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderNotices\Pages;

use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Services\Acquisition\TenderNoticeService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenderNotice extends CreateRecord
{
    protected static string $resource = TenderNoticeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TenderNoticeService::class)->create($data);
    }
}
