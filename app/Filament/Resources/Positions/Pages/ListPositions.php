<?php

declare(strict_types=1);

namespace App\Filament\Resources\Positions\Pages;

use App\Filament\Resources\Positions\PositionResource;
use App\Services\Personnel\PositionService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListPositions extends ListRecords
{
    protected static string $resource = PositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Kod arayuzde girilmez (D-130); servis basliktan benzersiz uretir.
            CreateAction::make()
                ->using(fn (array $data): Model => app(PositionService::class)->create($data)),
        ];
    }
}
