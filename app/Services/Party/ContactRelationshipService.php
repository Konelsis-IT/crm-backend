<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Enums\Party\PartyKind;
use App\Exceptions\Personnel\SelfParentNotAllowedException;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Services\AbstractService;
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
        $this->assertKinds((int) ($data['organization_party_id'] ?? 0), (int) ($data['contact_party_id'] ?? 0));
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
            (int) ($data['contact_party_id'] ?? $current->contact_party_id),
        );

        return parent::update($current, $data);
    }

    private function assertKinds(int $organizationId, int $contactId): void
    {
        if ($organizationId === $contactId) {
            throw SelfParentNotAllowedException::make();
        }

        $kinds = Party::query()->whereKey([$organizationId, $contactId])->pluck('party_kind', 'id');

        if ($kinds->get($organizationId) !== PartyKind::Organization || $kinds->get($contactId) !== PartyKind::Person) {
            throw SelfParentNotAllowedException::make();
        }
    }
}
