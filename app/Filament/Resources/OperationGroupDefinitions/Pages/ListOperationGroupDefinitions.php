<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationGroupDefinitions\Pages;

use App\Filament\Resources\OperationGroupDefinitions\OperationGroupDefinitionResource;
use App\Services\Project\OperationGroupDefinitionService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListOperationGroupDefinitions extends ListRecords
{
    protected static string $resource = OperationGroupDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Kod arayuzde girilmez (D-130); servis Turkce addan benzersiz uretir.
            CreateAction::make()
                ->using(fn (array $data): Model => app(OperationGroupDefinitionService::class)->create($data)),
        ];
    }
}
