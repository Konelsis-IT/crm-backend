<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffs\Pages;

use App\Filament\Resources\OperationHandoffs\OperationHandoffResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationHandoffs extends ListRecords
{
    protected static string $resource = OperationHandoffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
