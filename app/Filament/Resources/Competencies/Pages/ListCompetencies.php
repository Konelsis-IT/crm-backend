<?php

declare(strict_types=1);

namespace App\Filament\Resources\Competencies\Pages;

use App\Filament\Resources\Competencies\CompetencyResource;
use App\Services\Personnel\CompetencyService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListCompetencies extends ListRecords
{
    protected static string $resource = CompetencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Kod arayuzde girilmez (D-130); servis addan benzersiz uretir.
            CreateAction::make()
                ->using(fn (array $data): Model => app(CompetencyService::class)->create($data)),
        ];
    }
}
