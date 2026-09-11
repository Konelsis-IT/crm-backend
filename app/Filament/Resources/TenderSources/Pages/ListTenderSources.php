<?php

declare(strict_types=1);

namespace App\Filament\Resources\TenderSources\Pages;

use App\Exceptions\CodeAlreadyInUseException;
use Illuminate\Validation\ValidationException;
use App\Filament\Resources\TenderSources\TenderSourceResource;
use App\Services\Acquisition\TenderSourceService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListTenderSources extends ListRecords
{
    protected static string $resource = TenderSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    try {
                        return app(TenderSourceService::class)->create($data);
                    } catch (CodeAlreadyInUseException) {
                        throw ValidationException::withMessages([
                            'data.code' => __('tender_source.validation.code_taken'),
                        ]);
                    }
                }),
        ];
    }
}
