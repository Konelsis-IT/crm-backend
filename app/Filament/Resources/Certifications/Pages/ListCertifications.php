<?php

declare(strict_types=1);

namespace App\Filament\Resources\Certifications\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use App\Filament\Resources\Certifications\CertificationResource;
use App\Services\Personnel\CertificationService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListCertifications extends ListRecords
{
    protected static string $resource = CertificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(CertificationService::class)->create($data);
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.code' => __('certification.validation.code_taken'),
                        ]);
                    }
                }),
        ];
    }
}
