<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\Platform\Feature;
use App\Models\Platform\PlatformFeature;
use App\Services\AbstractService;
use Illuminate\Support\Collection;

/**
 * Ozellik anahtarlari tablosunu katalogla esitler (B39, D-128).
 *
 * - Katalogda olup tabloda olmayan ozellik, varsayilan durumuyla eklenir
 *   (Feature::defaultActive(): surum notlari kapali, digerleri acik).
 * - Ad, aciklama, karar, surum (B42, D-151), ust ozellik ve sira katalogdan
 *   guncellenir.
 * - Esitleme `is_active` degerine ASLA dokunmaz: o deger veritabanindan
 *   degistirilir (kullanici karari). Tek istisna surum yayinidir (D-153):
 *   `konelsis:release` yayinlanan surumle gelen kapali ozellikleri acar
 *   (openReleased). Katalogdan kalkan satir silinmez.
 *
 * Katalog esitlemesi bir is kaydi degildir (PersonnelQuickActionService ile
 * ayni gerekce): Personel Hareketleri'ne satir yazilmaz. Durumu degistiren
 * kisi zaten uygulamanin disindadir (veritabani).
 */
final class PlatformFeatureService extends AbstractService
{
    /**
     * Tablo katalogla ayni mi? Yalniz okur.
     *
     * @param  Collection<string, PlatformFeature>  $rows
     */
    public function outOfSync(Collection $rows): bool
    {
        $ids = $this->idsByCode($rows);

        foreach (Feature::cases() as $feature) {
            $row = $rows->get($feature->value);

            if ($row === null) {
                return true;
            }

            foreach ($this->expected($feature, $ids) as $column => $value) {
                if ($row->getAttribute($column) !== $value) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Eksik satirlari ekler, degisen tanimlari gunceller. Katalog sirasi ust
     * ozelligi alt ozellikten once verir; ust satirin kimligi ayni turda olusur.
     *
     * @param  Collection<string, PlatformFeature>  $rows
     */
    public function syncCatalog(Collection $rows): void
    {
        $this->transactions->run(function () use ($rows): void {
            $ids = $this->idsByCode($rows);

            foreach (Feature::cases() as $feature) {
                $attributes = $this->expected($feature, $ids);
                $row = $rows->get($feature->value);

                if ($row === null) {
                    $created = PlatformFeature::query()->create([
                        ...$attributes,
                        'code' => $feature->value,
                        'is_active' => $feature->defaultActive(),
                    ]);
                    $ids[$feature->value] = (int) $created->getKey();

                    continue;
                }

                $row->fill($attributes);

                if ($row->isDirty()) {
                    $row->save();
                }
            }
        });
    }

    /**
     * Veri dosyasindaki acik / kapali durumlari uygular (FeatureSeeder: yerelde
     * `konelsis:features:export` ile uretilen dosyanin canliya aktarimi). Yalniz
     * DBA / kurulum surecinde calisir, arayuzden cagrilmaz; bu, kullanicinin
     * veritabanindan yaptigi degisikligin toplu hali oldugu icin Personel
     * Hareketleri'ne yazilmaz. Katalogda ya da tabloda olmayan kod atlanir.
     *
     * @param  array<string, bool>  $states  kod => acik mi
     * @return array{changed: int, skipped: list<string>}
     */
    public function applyStates(array $states): array
    {
        return $this->transactions->run(function () use ($states): array {
            $rows = PlatformFeature::query()->whereIn('code', array_keys($states))->get()->keyBy('code');
            $changed = 0;
            $skipped = [];

            foreach ($states as $code => $active) {
                $row = $rows->get($code);

                if (Feature::tryFrom((string) $code) === null || $row === null) {
                    $skipped[] = (string) $code;

                    continue;
                }

                if ((bool) $row->is_active !== (bool) $active) {
                    $row->is_active = (bool) $active;
                    $row->save();
                    $changed++;
                }
            }

            return ['changed' => $changed, 'skipped' => $skipped];
        });
    }

    /**
     * Yayinlanan guncellemeyle gelen ozellikler (D-153): surumu ($after, $upTo]
     * araliginda olanlar. Ilk yayinda ($after null) yalniz $upTo surumundekiler;
     * daha eski surumlerde canlida kapatilmis ozelliklere dokunulmaz.
     *
     * @return list<Feature>
     */
    public static function releasedBetween(?string $after, string $upTo): array
    {
        return array_values(array_filter(
            Feature::cases(),
            static fn (Feature $feature): bool => $after === null
                ? version_compare($feature->version(), $upTo, '==')
                : version_compare($feature->version(), $after, '>') && version_compare($feature->version(), $upTo, '<='),
        ));
    }

    /**
     * Sira gelen guncellemenin ozelliklerini acar (D-153, 3 Ekim 2026 kullanici
     * karari: "sirasi gelen guncellemeyle acilacak"): yayinlanan araliktaki
     * kapali satirlar `is_active = 1` olur. Yalniz FeatureReleaseService cagirir;
     * hareket kaydini yayin yazar.
     *
     * @return list<string> acilan ozellik kodlari
     */
    public function openReleased(?string $after, string $upTo): array
    {
        $codes = array_map(static fn (Feature $feature): string => $feature->value, self::releasedBetween($after, $upTo));

        if ($codes === []) {
            return [];
        }

        return $this->transactions->run(function () use ($codes): array {
            $rows = PlatformFeature::query()
                ->whereIn('code', $codes)
                ->where('is_active', false)
                ->get();

            foreach ($rows as $row) {
                $row->is_active = true;
                $row->save();
            }

            return $rows->map(fn (PlatformFeature $row): string => (string) $row->code)->values()->all();
        });
    }

    /**
     * Katalogdan gelen tanim kolonlari (is_active haric). Surum kolonu B42 ile
     * gelir (D-151); uygulanmadan once yazilmaz.
     *
     * @param  array<string, int>  $ids
     * @return array<string, int|string|null>
     */
    private function expected(Feature $feature, array $ids): array
    {
        $parent = $feature->parent();

        $columns = [
            'parent_id' => $parent === null ? null : ($ids[$parent->value] ?? null),
            'name' => $feature->title(),
            'description' => $feature->description(),
            'decision_ref' => $feature->decision(),
            'sort_order' => $feature->sortOrder(),
        ];

        if (SchemaReadiness::hasBatch('B42')) {
            $columns['version'] = $feature->version();
        }

        return $columns;
    }

    /**
     * @param  Collection<string, PlatformFeature>  $rows
     * @return array<string, int>
     */
    private function idsByCode(Collection $rows): array
    {
        return $rows->map(fn (PlatformFeature $row): int => (int) $row->getKey())->all();
    }
}
