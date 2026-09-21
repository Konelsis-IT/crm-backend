<?php

declare(strict_types=1);

namespace App\Filament\Resources\Associations\Pages;

use App\Filament\Resources\Associations\AssociationResource;
use App\Filament\Resources\Parties\Pages\EditParty;

/** Dernek duzenle: Taraflar ile ayni kayit akisi (PartyService). */
class EditAssociation extends EditParty
{
    protected static string $resource = AssociationResource::class;
}
