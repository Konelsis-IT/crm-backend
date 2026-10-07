<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Enums\Party\PartyKind;
use App\Enums\Party\PartyStatus;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\RecordArchivedException;
use App\Models\Party\OrganizationProfile;
use App\Models\Party\Party;
use App\Models\Party\PersonProfile;
use App\Services\AbstractService;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Party (musteri/tedarikci/kisi) servisi (10 SS1, D-28).
 *
 * create/update override edilmistir: party_no otomatik uretilir,
 * normalized_name ve duplicate_check_hash hesaplanir, party_kind'a gore
 * tam olarak bir alt profil (organization_profiles / person_profiles)
 * yazilir. Profil verisi formdan 'organization_profile' / 'person_profile'
 * anahtarlariyla gelir.
 *
 * D-95 / B28 (16 Eylul 2026): olusturmada 'party_roles' satirlari ve her iki
 * islemde kuruma ait 'communication_points' satirlari da ayni islem icinde
 * yazilir; boylece taraf, tipleri ve kanallari ya birlikte kaydedilir ya
 * da hicbiri.
 *
 * B33 (21 Eylul 2026): faaliyet satirlari ('activity_areas' anahtari) da ayni
 * islemde tam liste olarak yazilir; koken ve rakip firma taraf kolonudur.
 *
 * D-170 (7 Ekim 2026): kurulusun adi kisa ad (trade_name) ve uzun ad / unvan
 * (legal_name) olarak ikiye ayrildi; gorunen ad her zaman kisa ad, kisa ad
 * bossa uzun addir (organizationNames). Birlestirilen taraf (status merged,
 * PartyMergeService) degistirilemez ve arsivden cikarilamaz.
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
            $roles = (array) ($data['party_roles'] ?? []);
            $channels = array_key_exists('communication_points', $data) ? (array) $data['communication_points'] : null;
            $activities = array_key_exists('activity_areas', $data) ? (array) $data['activity_areas'] : null;
            unset($data['organization_profile'], $data['person_profile'], $data['party_roles'], $data['communication_points'], $data['activity_areas']);

            $data['party_no'] = $this->generatePartyNo();

            if (self::kindOf($data['party_kind'] ?? null) === PartyKind::Organization) {
                [$data, $organization] = $this->organizationNames($data, $organization, null);
            }

            $data = $this->normalize($data, $organization['tax_number'] ?? null);

            /** @var Party $party */
            $party = parent::create($data);

            $this->syncProfile($party, $organization, $person);
            $this->syncRoles($party, $roles);
            $this->syncOwnChannels($party, $channels);
            $this->syncActivityAreas($party, $activities);

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

            // D-170: birlestirilmis taraf (status merged) arsivdedir; tasinan
            // kayitlar hedeftedir ve kaynak artik degistirilmez.
            if ($current->status === PartyStatus::Merged) {
                throw RecordArchivedException::make();
            }

            $organization = isset($data['organization_profile']) ? (array) $data['organization_profile'] : null;
            $person = isset($data['person_profile']) ? (array) $data['person_profile'] : null;
            $channels = array_key_exists('communication_points', $data) ? (array) $data['communication_points'] : null;
            $activities = array_key_exists('activity_areas', $data) ? (array) $data['activity_areas'] : null;
            unset($data['organization_profile'], $data['person_profile'], $data['communication_points'], $data['activity_areas'], $data['party_roles'], $data['party_no'], $data['party_kind']);

            $data = [
                'display_name' => $current->display_name,
                'country_code' => $current->country_code,
                ...$data,
            ];

            if ($current->party_kind === PartyKind::Organization) {
                [$data, $organization] = $this->organizationNames($data, $organization, $current);
            }

            $taxNumber = $organization['tax_number'] ?? $current->organizationProfile?->tax_number;
            $data = $this->normalize($data, is_string($taxNumber) ? $taxNumber : null);

            /** @var Party $party */
            $party = parent::update($current, $data);

            $this->syncProfile($party, $organization, $person);
            $this->syncOwnChannels($party, $channels);
            $this->syncActivityAreas($party, $activities);

            return $party;
        });
    }

    /**
     * Kurulusun kisa ve uzun adi (D-170, 7 Ekim 2026 kullanici talimati: "Taraf
     * sistemine kisa isim ve uzun isim seklinde ad kismi 2'ye ayrilacak ...
     * ikisini bilmediklerimizin hepsi uzun isim olarak kaydedilsin").
     *
     * - Uzun ad (unvan) = organization_profiles.legal_name (zorunlu), kisa ad =
     *   organization_profiles.trade_name (bos olabilir).
     * - Gorunen ad (parties.display_name) her zaman kisa ad, kisa ad bossa uzun
     *   addir; formdan ya da cagirandan gelen serbest gorunen ad bu kurali
     *   bozamaz.
     * - Ad yalniz gorunen ad olarak geldiyse (hizli firma ekleme, ice aktarma,
     *   eski cagiranlar) ve profilin gosterdigi addan farkliysa: kisa ad
     *   doluysa kisa ad, degilse uzun ad o ad olur (yeni kayitta uzun ad).
     * - Profil adlari degismediyse profil verisine ad eklenmez (gereksiz yazma
     *   olmaz).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $organization
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private function organizationNames(array $data, ?array $organization, ?Party $current): array
    {
        $profile = $current?->organizationProfile;
        $hasLegal = $organization !== null && array_key_exists('legal_name', $organization);
        $hasTrade = $organization !== null && array_key_exists('trade_name', $organization);
        $legal = self::cleanName($hasLegal ? $organization['legal_name'] : $profile?->legal_name);
        $trade = self::cleanName($hasTrade ? $organization['trade_name'] : $profile?->trade_name);

        if (! $hasLegal && ! $hasTrade) {
            $display = self::cleanName($data['display_name'] ?? null);

            if ($display !== null && $display !== ($trade ?? $legal)) {
                if ($trade !== null) {
                    $trade = $display;
                } else {
                    $legal = $display;
                }
            }
        }

        $legal ??= $trade ?? self::cleanName($data['display_name'] ?? null) ?? '';
        $changed = $profile === null
            || self::cleanName($profile->legal_name) !== $legal
            || self::cleanName($profile->trade_name) !== $trade;

        if ($hasLegal || $hasTrade || $changed) {
            $organization = [...($organization ?? []), 'legal_name' => $legal, 'trade_name' => $trade];
        }

        $data['display_name'] = $trade ?? $legal;

        return [$data, $organization];
    }

    private static function cleanName(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $name = Str::of((string) $value)->squish()->value();

        return $name === '' ? null : $name;
    }

    private static function kindOf(mixed $value): ?PartyKind
    {
        return $value instanceof PartyKind ? $value : PartyKind::tryFrom(is_scalar($value) ? (string) $value : '');
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

        // Koken ve rakip firma (B33): grup uygulanmadan kolon yoktur.
        if (! SchemaReadiness::hasBatch('B33')) {
            unset($data['origin'], $data['is_competitor']);
        } elseif (array_key_exists('origin', $data) && blank($data['origin'])) {
            $data['origin'] = null;
        }
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
            // D-170: uzun ad verilmediyse var olan profilin uzun adi korunur
            // (gorunen ad kisa ad olabilir); profil yoksa gorunen ad uzun addir.
            if (blank($organization['legal_name'] ?? null)) {
                unset($organization['legal_name']);

                if (! OrganizationProfile::query()->whereKey($party->getKey())->exists()) {
                    $organization['legal_name'] = $party->display_name;
                }
            }

            OrganizationProfile::query()->updateOrCreate(['party_id' => $party->getKey()], $organization);
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
     * Tarafi arsivler (S3, D-99): kritik kayitta hard delete yoktur, kayit
     * archived_at ile kapatilir. Arsivli taraf listede gorunmez,
     * "Arsivlenenler" suzgeciyle bulunur ve geri alinabilir. Hareket kaydi:
     * party.archived.
     */
    public function archive(Model|int|string $record, string $reason): Model
    {
        return $this->transactions->run(function () use ($record, $reason): Model {
            /** @var Party $party */
            $party = $this->lockForUpdate($record);

            if ($party->archived_at !== null) {
                throw InvalidTransitionException::make();
            }

            $party->forceFill([
                'archived_at' => Carbon::now('UTC'),
                'archived_by_personnel_id' => app(ActorContext::class)->personnelId(),
                'archive_reason' => Str::of($reason)->squish()->limit(100, '')->value(),
            ]);
            $this->saveWithoutVersion($party);
            $this->recordActivity($party, 'archived', ['archive_reason' => $party->archive_reason]);

            return $party;
        });
    }

    /** Arsivli tarafi geri alir (archived_at temizlenir). Hareket kaydi: party.restored. */
    public function restore(Model|int|string $record): Model
    {
        return $this->transactions->run(function () use ($record): Model {
            /** @var Party $party */
            $party = $this->lockForUpdate($record);

            // D-170: birlestirilen taraf (status merged) arsivden cikarilamaz.
            if ($party->archived_at === null || $party->status === PartyStatus::Merged) {
                throw InvalidTransitionException::make();
            }

            $reason = $party->archive_reason;
            $party->forceFill(['archived_at' => null, 'archived_by_personnel_id' => null, 'archive_reason' => null]);
            $this->saveWithoutVersion($party);
            $this->recordActivity($party, 'restored', ['archive_reason' => $reason]);

            return $party;
        });
    }

    /**
     * Taraf tipi satirlari (D-95): yeni kayitla ayni islemde acilir.
     *
     * @param  array<int|string, mixed>  $rows
     */
    private function syncRoles(Party $party, array $rows): void
    {
        foreach ($rows as $row) {
            if (! is_array($row) || blank($row['role_code'] ?? null)) {
                continue;
            }

            app(PartyRoleService::class)->create(['party_id' => $party->getKey(), ...$row]);
        }
    }

    /**
     * Kuruma ait iletisim kanallari (B28): anahtar hic verilmediyse dokunulmaz.
     *
     * @param  array<int|string, mixed>|null  $rows
     */
    private function syncOwnChannels(Party $party, ?array $rows): void
    {
        if ($rows === null || ! SchemaReadiness::hasBatch('B27')) {
            return;
        }

        app(CommunicationPointService::class)->syncOwnChannels($party, $rows);
    }

    /**
     * Faaliyet satirlari (B33): anahtar hic verilmediyse dokunulmaz; verilen
     * liste tam listedir.
     *
     * @param  array<int|string, mixed>|null  $rows
     */
    private function syncActivityAreas(Party $party, ?array $rows): void
    {
        if ($rows === null || ! SchemaReadiness::hasBatch('B33')) {
            return;
        }

        app(PartyActivityAreaService::class)->sync($party, $rows);
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
