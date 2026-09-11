<?php

declare(strict_types=1);

namespace App\Filament\Resources\BusinessCases\Pages;

use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBusinessCases extends ListRecords
{
    protected static string $resource = BusinessCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('business_case.actions.create'))
                ->icon(Heroicon::OutlinedBriefcase),
        ];
    }
}
