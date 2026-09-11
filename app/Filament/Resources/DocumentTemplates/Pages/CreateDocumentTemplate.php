<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentTemplates\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use App\Services\Document\DocumentTemplateService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateDocumentTemplate extends CreateRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(DocumentTemplateService::class)->create($data);
        } catch (CodeAlreadyInUseException) {
            throw ValidationException::withMessages([
                'data.code' => __('document_template.validation.code_taken'),
            ]);
        }
    }
}
