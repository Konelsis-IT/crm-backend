<?php

declare(strict_types=1);

namespace App\Query\Party;

use App\Enums\Party\PartyRoleCode;
use App\Models\Party\Party;
use App\Models\Party\PartyRole;
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
            ->whereHas('party', fn (Builder $party): Builder => $party->whereNull('archived_at'))
            ->get(['role_code', 'party_id'])
            ->groupBy(static fn (PartyRole $role): string => (string) $role->role_code?->value)
            ->map(static fn ($rows): int => $rows->pluck('party_id')->unique()->count())
            ->all();
    }

    /** Listede gosterilecek toplam taraf sayisi (arsivliler haric). */
    public function total(): int
    {
        return Party::query()->whereNull('archived_at')->count();
    }

    /** Tarafin gorunen adi (sihirbaz ozet karti icin); taraf yoksa null. */
    public function displayName(int $id): ?string
    {
        $name = Party::query()->whereKey($id)->value('display_name');

        return $name === null ? null : (string) $name;
    }
}
