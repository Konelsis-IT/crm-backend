<?php

declare(strict_types=1);

namespace App\Query\Party;

use App\Enums\Party\PartyRoleCode;
use App\Enums\Party\PartyStatus;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Models\Party\PartyRole;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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
    /**
     * Verilen tipe sahip (acik) taraflar. Isveren, B46 uygulanmamis ortamdaki
     * eski Musteri / Yatirimci satirlarini da kapsar (D-167).
     */
    public function withRole(Builder $query, PartyRoleCode $code): Builder
    {
        return $query->whereHas(
            'roles',
            fn (Builder $roles): Builder => $roles
                ->whereIn('role_code', $code->canonical()->storedValues())
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

    /**
     * Ada gore arama (D-170): gorunen ad, kisa ad ya da uzun ad (unvan). Tablo
     * aramasi ve secim listeleri ayni kurali kullanir.
     */
    public function searchByName(Builder $query, string $search): Builder
    {
        $term = '%'.trim($search).'%';
        $model = $query->getModel();

        return $query->where(fn (Builder $inner): Builder => $inner
            ->where($model->qualifyColumn('display_name'), 'like', $term)
            ->orWhereHas('organizationProfile', fn (Builder $profile): Builder => $profile
                ->where('legal_name', 'like', $term)
                ->orWhere('trade_name', 'like', $term)));
    }

    /** Taraf listesi: dernekler haric, kisa / uzun ad icin profil birlikte yuklenir (D-170). */
    public function forList(Builder $query): Builder
    {
        return $this->withoutAssociations($query->with('organizationProfile'));
    }

    /**
     * Genel arama (D-170): kisa ve uzun ad icin profil yuklenir; baska tarafa
     * birlestirilmis kayitlar sonuclarda cikmaz.
     */
    public function forGlobalSearch(Builder $query): Builder
    {
        return $query
            ->with('organizationProfile')
            ->where($query->getModel()->qualifyColumn('status'), '<>', PartyStatus::Merged->value);
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
     * Eski Musteri / Yatirimci satirlari Isveren'de sayilir (D-167).
     *
     * @return array<string, int>
     */
    public function roleCounts(): array
    {
        return PartyRole::query()
            ->whereNull('valid_until')
            ->whereHas('party', fn (Builder $party): Builder => $this->withoutAssociations($party->whereNull('archived_at')))
            ->get(['role_code', 'party_id'])
            ->groupBy(static fn (PartyRole $role): string => (string) $role->role_code?->canonical()->value)
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
            // D-170: kisa ad ya da uzun ad (unvan) ile de bulunur.
            ->when(trim($term) !== '', fn (Builder $query): Builder => $this->searchByName($query, $term))
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

    /**
     * Ayni adla kayitli taraf (arsivliler dahil; kucuk harf ve bosluk farki
     * gozetilmez, PartyService::normalize ile ayni kural). Hizli firma ekleme
     * cift kaydi onler (D-156).
     *
     * @return array{id: int, name: string, archived: bool}|null
     */
    public function sameName(string $name): ?array
    {
        $normalized = Str::of($name)->lower()->squish()->value();

        if ($normalized === '') {
            return null;
        }

        /** @var Party|null $party */
        $party = Party::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('normalized_name', $normalized)
                // D-170: kurulusun kisa adi ya da uzun adi (unvani) da ayni addir.
                ->orWhereHas('organizationProfile', fn (Builder $profile): Builder => $profile
                    ->where('legal_name', Str::of($name)->squish()->value())
                    ->orWhere('trade_name', Str::of($name)->squish()->value())))
            // D-170: baska tarafa birlestirilmis kayit onerilmez.
            ->where('status', '<>', PartyStatus::Merged->value)
            // MySQL artan siralamada NULL once gelir: aktif kayit onde.
            ->orderBy('archived_at')
            ->first(['id', 'display_name', 'archived_at']);

        return $party === null ? null : [
            'id' => (int) $party->getKey(),
            'name' => (string) $party->display_name,
            'archived' => $party->archived_at !== null,
        ];
    }

    /** Tarafin gorunen adi (sihirbaz ozet karti icin); taraf yoksa null. */
    public function displayName(int $id): ?string
    {
        $name = Party::query()->whereKey($id)->value('display_name');

        return $name === null ? null : (string) $name;
    }
}
