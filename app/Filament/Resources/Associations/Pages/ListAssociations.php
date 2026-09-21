<?php

declare(strict_types=1);

namespace App\Filament\Resources\Associations\Pages;

use App\Filament\Exports\AssociationExporter;
use App\Filament\Resources\Associations\AssociationResource;
use App\Filament\Support\ExportActions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Dernekler listesi: sekme yoktur; "Dernek olustur" ile yeni kayit acilir. */
class ListAssociations extends ListRecords
{
    protected static string $resource = AssociationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::table(AssociationExporter::class),
            CreateAction::make(),
        ];
    }
}
