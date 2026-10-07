<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Enums\Party\CommunicationChannelType;
use App\Enums\Party\ContactRelationshipRole;
use App\Enums\Party\PartyKind;
use App\Exceptions\Personnel\SelfParentNotAllowedException;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Services\AbstractService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Kurulus-kisi iliskisi servisi (10 SS1.7): kurulus tarafi organization,
 * kisi tarafi person olmali; valid_from varsayilani simdi.
 */
final class ContactRelationshipService extends AbstractService
{
    protected string $model = ContactRelationship::class;

    /** @var list<string> */
    protected array $with = ['organization', 'contact'];

    protected string $orderBy = 'valid_from';

    protected string $orderDirection = 'desc';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertKinds((int) ($data['organization_party_id'] ?? 0), $data['contact_party_id'] ?? null);
        $data['valid_from'] ??= Carbon::now('UTC');

        return parent::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var ContactRelationship $current */
        $current = $this->show($record);
        $this->assertKinds(
            (int) ($data['organization_party_id'] ?? $current->organization_party_id),
            $data['contact_party_id'] ?? $current->contact_party_id,
        );

        return parent::update($current, $data);
    }

    /**
     * Hizli kisi ekleme (D-167, 6 Ekim 2026 kullanici istegi: "gorusulen kisi o
     * ekranda + butonu ile modal icinde eklenebilir olmalidir"). Kisi verilen
     * kurum tarafina (gorusme notunun / planinin tarafina) yazilir; cep telefonu
     * ve e-posta verildiyse kisinin iletisim kanali olarak ayni islemde eklenir.
     * Hareket kayitlari: contact_relationship.created, communication_point.created.
     *
     * @param  array<string, mixed>  $data  contact_name, relationship_role, department_note, phone, email
     */
    public function quickCreate(int $organizationPartyId, array $data): ContactRelationship
    {
        return $this->transactions->run(function () use ($organizationPartyId, $data): ContactRelationship {
            $role = $data['relationship_role'] ?? null;
            $role = $role instanceof BackedEnum ? (string) $role->value : (filled($role) ? (string) $role : ContactRelationshipRole::Other->value);

            /** @var ContactRelationship $contact */
            $contact = $this->create([
                'organization_party_id' => $organizationPartyId,
                'contact_name' => trim((string) ($data['contact_name'] ?? '')),
                'relationship_role' => $role,
                'department_note' => filled($data['department_note'] ?? null) ? trim((string) $data['department_note']) : null,
            ]);

            $channels = [
                CommunicationChannelType::Mobile->value => $data['phone'] ?? null,
                CommunicationChannelType::Email->value => $data['email'] ?? null,
            ];

            foreach ($channels as $type => $value) {
                if (blank($value)) {
                    continue;
                }

                app(CommunicationPointService::class)->create([
                    'party_id' => $organizationPartyId,
                    'contact_relationship_id' => $contact->getKey(),
                    'channel_type' => $type,
                    'value' => trim((string) $value),
                    'is_primary' => false,
                ]);
            }

            return $contact;
        });
    }

    /**
     * Kisi icin ayri taraf kaydi zorunlu degildir (D-94); bagli bir taraf
     * verildiyse kurum-kisi esleşmesi yine dogrulanir.
     */
    private function assertKinds(int $organizationId, int|string|null $contactId): void
    {
        $contactId = $contactId === null || $contactId === '' ? null : (int) $contactId;

        if ($contactId === null) {
            return;
        }

        if ($organizationId === $contactId) {
            throw SelfParentNotAllowedException::make();
        }

        $kinds = Party::query()->whereKey([$organizationId, $contactId])->pluck('party_kind', 'id');

        if ($kinds->get($organizationId) !== PartyKind::Organization || $kinds->get($contactId) !== PartyKind::Person) {
            throw SelfParentNotAllowedException::make();
        }
    }
}
