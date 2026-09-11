<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Enums\Party\PartyKind;
use App\Models\Party\OrganizationProfile;
use App\Models\Party\Party;
use App\Models\Party\PersonProfile;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Party (musteri/tedarikci/kisi) servisi (10 SS1, D-28).
 *
 * create/update override edilmistir: party_no otomatik uretilir,
 * normalized_name ve duplicate_check_hash hesaplanir, party_kind'a gore
 * tam olarak bir alt profil (organization_profiles / person_profiles)
 * yazilir. Profil verisi formdan 'organization_profile' / 'person_profile'
 * anahtarlariyla gelir.
 */
final class PartyService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['country', 'organizationProfile', 'personProfile', 'roles'];

    protected string $orderBy = 'display_name';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $organization = (array) ($data['organization_profile'] ?? []);
            $person = (array) ($data['person_profile'] ?? []);
            unset($data['organization_profile'], $data['person_profile']);

            $data['party_no'] = $this->generatePartyNo();
            $data = $this->normalize($data, $organization['tax_number'] ?? null);

            /** @var Party $party */
            $party = parent::create($data);

            $this->syncProfile($party, $organization, $person);

            return $party;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var Party $current */
            $current = $this->show($record);

            $organization = isset($data['organization_profile']) ? (array) $data['organization_profile'] : null;
            $person = isset($data['person_profile']) ? (array) $data['person_profile'] : null;
            unset($data['organization_profile'], $data['person_profile'], $data['party_no'], $data['party_kind']);

            $taxNumber = $organization['tax_number'] ?? $current->organizationProfile?->tax_number;
            $data = $this->normalize([
                'display_name' => $current->display_name,
                'country_code' => $current->country_code,
                ...$data,
            ], is_string($taxNumber) ? $taxNumber : null);

            /** @var Party $party */
            $party = parent::update($current, $data);

            $this->syncProfile($party, $organization, $person);

            return $party;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?string $taxNumber): array
    {
        $displayName = trim((string) ($data['display_name'] ?? ''));
        $normalized = Str::of($displayName)->lower()->squish()->value();

        $data['display_name'] = $displayName;
        $data['normalized_name'] = $normalized;
        $data['duplicate_check_hash'] = hash('sha256', implode('|', [
            $normalized,
            strtoupper((string) ($data['country_code'] ?? '')),
            preg_replace('/\D+/', '', (string) $taxNumber) ?? '',
        ]));

        return $data;
    }

    /**
     * @param  array<string, mixed>|null  $organization
     * @param  array<string, mixed>|null  $person
     */
    private function syncProfile(Party $party, ?array $organization, ?array $person): void
    {
        if ($party->party_kind === PartyKind::Organization && $organization !== null) {
            OrganizationProfile::query()->updateOrCreate(
                ['party_id' => $party->getKey()],
                [...$organization, 'legal_name' => filled($organization['legal_name'] ?? null) ? $organization['legal_name'] : $party->display_name],
            );
        }

        if ($party->party_kind === PartyKind::Person && $person !== null) {
            [$given, $family] = $this->splitName($party->display_name);

            PersonProfile::query()->updateOrCreate(
                ['party_id' => $party->getKey()],
                [
                    ...$person,
                    'given_name' => filled($person['given_name'] ?? null) ? $person['given_name'] : $given,
                    'family_name' => filled($person['family_name'] ?? null) ? $person['family_name'] : $family,
                ],
            );
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $displayName): array
    {
        $parts = preg_split('/\s+/', trim($displayName)) ?: [];
        $family = count($parts) > 1 ? (string) array_pop($parts) : '-';

        return [implode(' ', $parts) ?: $displayName, $family];
    }

    private function generatePartyNo(): string
    {
        $sequence = Party::query()->count() + 1;
        $partyNo = sprintf('PTY-%05d', $sequence);

        while (Party::query()->where('party_no', $partyNo)->exists()) {
            $sequence++;
            $partyNo = sprintf('PTY-%05d', $sequence);
        }

        return $partyNo;
    }
}
