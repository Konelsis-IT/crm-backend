<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentTemplates\Pages;

use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use App\Services\Document\DocumentTemplateService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDocumentTemplate extends CreateRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    /** Kod arayuzde girilmez (D-130); servis addan benzersiz uretir. */
    protected function handleRecordCreation(array $data): Model
    {
        return app(DocumentTemplateService::class)->create($data);
    }
}
