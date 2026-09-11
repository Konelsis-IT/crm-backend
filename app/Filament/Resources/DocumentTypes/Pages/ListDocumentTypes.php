<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentTypes\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use App\Filament\Resources\DocumentTypes\DocumentTypeResource;
use App\Services\Document\DocumentTypeService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListDocumentTypes extends ListRecords
{
    protected static string $resource = DocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(DocumentTypeService::class)->create($data);
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.code' => __('document_type.validation.code_taken'),
                        ]);
                    }
                }),
        ];
    }
}
