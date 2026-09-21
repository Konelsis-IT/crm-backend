<?php

declare(strict_types=1);

namespace App\Query\Party;

use App\Enums\Party\PartyRoleCode;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Models\Party\PartyRole;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;

/**
 * Taraf listesi sorgulari (D-95).
 *
 * Taraf tipi (party_roles) tarihcelidir: bitis tarihi bos olan satir o an
 * gecerli tiptir. Bir taraf ayni anda birden fazla tipe sahip olabilir.
 *
 * Arsiv (S3, D-99): archived_at dolu taraflar varsayilan listede ve sekme
 * sayimlarinda yer almaz; "Arsivlenenler" suzgeciyle gorulur.
 */
final class PartyQueries
{
    /** Verilen tipe sahip (acik) taraflar. */
    public function withRole(Builder $query, PartyRoleCode $code): Builder
    {
        return $query->whereHas(
            'roles',
            fn (Builder $roles): Builder => $roles
                ->where('role_code', $code->value)
                ->whereNull('valid_until'),
        );
    }

    /**
     * Dernekler (Dernek / oda tipi, B33) Taraflar listesinde yer almaz; ayri
     * menuden (Dernekler) yonetilir (21 Eylul 2026 kullanici karari).
     */
    public function withoutAssociations(Builder $query): Builder
    {
        if (! SchemaReadiness::hasBatch('B33')) {
            return $query;
        }

        return $query->whereDoesntHave(
            'roles',
            fn (Builder $roles): Builder => $roles
                ->where('role_code', PartyRoleCode::Association->value)
                ->whereNull('valid_until'),
        );
    }

    /** Arsiv suzgeci: active (varsayilan) / archived / all. */
    public function archiveScope(Builder $query, string $mode): Builder
    {
        return match ($mode) {
            'archived' => $query->whereNotNull('archived_at'),
            'all' => $query,
            default => $query->whereNull('archived_at'),
        };
    }

    /**
     * Tip basina acik taraf sayisi (tek sorgu); arsivli taraflar sayilmaz.
     *
     * @return array<string, int>
     */
    public function roleCounts(): array
    {
        return PartyRole::query()
            ->whereNull('valid_until')
            ->whereHas('party', fn (Builder $party): Builder => $this->withoutAssociations($party->whereNull('archived_at')))
            ->get(['role_code', 'party_id'])
            ->groupBy(static fn (PartyRole $role): string => (string) $role->role_code?->value)
            ->map(static fn ($rows): int => $rows->pluck('party_id')->unique()->count())
            ->all();
    }

    /** Listede gosterilecek toplam taraf sayisi (arsivliler ve dernekler haric). */
    public function total(): int
    {
        return $this->withoutAssociations(Party::query()->whereNull('archived_at'))->count();
    }

    /**
     * Aranabilir taraf secimi (arsivliler haric): id => ad.
     *
     * @return array<int, string>
     */
    public function searchOptions(string $term, int $limit = 50): array
    {
        return Party::query()
            ->whereNull('archived_at')
            ->when(trim($term) !== '', fn (Builder $query): Builder => $query->where('display_name', 'like', '%'.trim($term).'%'))
            ->orderBy('display_name')
            ->limit($limit)
            ->pluck('display_name', 'id')
            ->all();
    }

    /**
     * Tarafin yetkili kisileri: id => ad.
     *
     * @return array<int, string>
     */
    public function contactOptions(?int $partyId): array
    {
        if ($partyId === null || $partyId <= 0) {
            return [];
        }

        return ContactRelationship::query()
            ->where('organization_party_id', $partyId)
            ->with('contact')
            ->get()
            ->mapWithKeys(fn (ContactRelationship $contact): array => [(int) $contact->getKey() => $contact->displayName()])
            ->sort()
            ->all();
    }

    /** Tarafin gorunen adi (sihirbaz ozet karti icin); taraf yoksa null. */
    public function displayName(int $id): ?string
    {
        $name = Party::query()->whereKey($id)->value('display_name');

        return $name === null ? null : (string) $name;
    }
}
