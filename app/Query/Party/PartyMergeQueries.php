<?php

declare(strict_types=1);

namespace App\Query\Party;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Taraf birlestirme okumalari (D-170, PartyMergeService).
 *
 * REFERENCES, parties.id'ye bakan butun kolonlardir (information_schema ile
 * 7 Ekim 2026'da cikarildi: 29 yabanci anahtar; organization_profiles.party_id
 * ve person_profiles.party_id profilin kendisidir, tasinmaz). Yeni bir tablo
 * tarafa baglanirsa buraya eklenir. Bu ortamda olmayan tablo / kolon (grubu
 * uygulanmamis) atlanir.
 */
final class PartyMergeQueries
{
    /**
     * Tablo => taraf kolonlari. Sira, birlestirmenin isleme sirasidir.
     *
     * @var array<string, list<string>>
     */
    public const REFERENCES = [
        'party_roles' => ['party_id'],
        'addresses' => ['party_id'],
        'contact_relationships' => ['organization_party_id', 'contact_party_id'],
        'communication_points' => ['party_id'],
        'party_licenses' => ['party_id'],
        'party_certificates' => ['party_id'],
        'party_annual_reviews' => ['party_id'],
        'party_activity_areas' => ['party_id'],
        'party_meeting_notes' => ['party_id'],
        'meeting_plans' => ['party_id'],
        'business_cases' => ['primary_party_id'],
        'business_development_activities' => ['party_id'],
        'business_development_activity_participants' => ['contact_party_id'],
        'tender_notices' => ['issuer_party_id'],
        'contracts' => ['customer_party_id'],
        'contract_parties' => ['party_id'],
        'contract_obligations' => ['responsible_party_id'],
        'projects' => ['customer_party_id'],
        'project_supply_items' => ['supplier_party_id'],
        'commercial_clarifications' => ['customer_contact_party_id'],
        'transmittals' => ['recipient_party_id'],
        'work_requests' => ['customer_party_id'],
        'work_items' => ['requester_party_id', 'waiting_party_id'],
        'organization_profiles' => ['group_parent_party_id'],
        'parties' => ['merged_into_party_id'],
    ];

    /** Birincil anahtari `id` olmayan tablolar. */
    private const KEYS = ['organization_profiles' => 'party_id'];

    /** @var array<string, bool> */
    private array $columns = [];

    /**
     * Bu ortamda var olan taraf kolonlari.
     *
     * @return array<string, list<string>>
     */
    public function references(): array
    {
        $result = [];

        foreach (self::REFERENCES as $table => $columns) {
            $present = array_values(array_filter($columns, fn (string $column): bool => $this->hasColumn($table, $column)));

            if ($present !== []) {
                $result[$table] = $present;
            }
        }

        return $result;
    }

    public function keyOf(string $table): string
    {
        return self::KEYS[$table] ?? 'id';
    }

    public function hasColumn(string $table, string $column): bool
    {
        $cacheKey = $table.'.'.$column;

        if (! array_key_exists($cacheKey, $this->columns)) {
            try {
                $this->columns[$cacheKey] = Schema::hasTable($table) && Schema::hasColumn($table, $column);
            } catch (Throwable) {
                $this->columns[$cacheKey] = false;
            }
        }

        return $this->columns[$cacheKey];
    }

    /**
     * Tarafa bakan satirlar (anahtar + istenen kolonlar), anahtar sirasiyla.
     *
     * @param  list<string>  $select
     * @return Collection<int, object>
     */
    public function rows(string $table, string $column, int $partyId, array $select = []): Collection
    {
        $key = $this->keyOf($table);
        $select = array_values(array_unique([$key, $column, ...array_filter($select, fn (string $name): bool => $this->hasColumn($table, $name))]));

        return DB::table($table)
            ->where($column, $partyId)
            ->orderBy($key)
            ->get($select);
    }
}
