<?php

declare(strict_types=1);

namespace App\Filament\Resources\Associations\Pages;

use App\Enums\Party\PartyKind;
use App\Enums\Party\PartyRoleCode;
use App\Enums\Party\PartyRoleStatus;
use App\Filament\Resources\Associations\AssociationResource;
use App\Filament\Resources\Parties\Pages\CreateParty;
use Illuminate\Database\Eloquent\Model;

/**
 * Dernek olustur: taraf tipi secilmez, kayit kurulus olarak ve "Dernek / oda"
 * tipiyle acilir. Yazma Taraflar ile ayni servisten gecer (PartyService).
 */
class CreateAssociation extends CreateParty
{
    protected static string $resource = AssociationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['party_kind'] = PartyKind::Organization->value;

        return parent::handleRecordCreation($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected function roleRows(array &$data): array
    {
        unset($data['party_roles']);

        return [[
            'role_code' => PartyRoleCode::Association->value,
            'status' => PartyRoleStatus::Active->value,
            'approved_by_personnel_id' => null,
            'valid_from' => null,
            'valid_until' => null,
        ]];
    }
}
