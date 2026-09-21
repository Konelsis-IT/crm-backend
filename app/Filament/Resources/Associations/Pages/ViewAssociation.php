<?php

declare(strict_types=1);

namespace App\Filament\Resources\Associations\Pages;

use App\Filament\Exports\AssociationExporter;
use App\Filament\Resources\Associations\AssociationResource;
use App\Filament\Resources\Parties\Pages\ViewParty;

/** Dernek ayrintisi: taraf kartiyla ayni yapi. */
class ViewAssociation extends ViewParty
{
    protected static string $resource = AssociationResource::class;

    protected static function exporter(): string
    {
        return AssociationExporter::class;
    }
}
