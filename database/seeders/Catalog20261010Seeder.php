<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Procurement\CatalogItemKind;
use App\Exceptions\Procurement\CatalogItemExistsException;
use App\Models\Procurement\CatalogItem;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\CatalogItemService;
use App\Services\Support\TransactionRunner;
use App\Support\Procurement\CatalogNames;
use Database\Seeders\Support\ProtectedSeeder;
use Throwable;

/**
 * Sabit katalog, 10.10.2026 (D-189; kullanici: "urunler - hizmetler - genel
 * giderler - idari kadro yapisi gibi kavramlar sisteme sabit olarak girilmeli
 * ve olusturulmalidir"; ornek olarak Kartal RES Hibrit GES maliyet Excel'i).
 *
 * - Urun / Hizmet, Idari kadro, Genel gider: database/seeders/data/
 *   catalog_2026_10_10.php (Excel'deki kalem adlari, bolum > grup kategorisi;
 *   ayni ad bir kez, 311 kalem).
 * - Sirket ici: kullanicinin verdigi ornekler (kalem, defter, bilgisayar,
 *   yazici, yazilim, sunucu, Claude) yenileme donemiyle; kirtasiye onaysiz.
 *   D-190: tur Urun / Hizmet, kullanim yeri Sirket ici; Excel kalemlerinin
 *   kullanim yeri Saha.
 *
 * Satir anahtari tur ailesi + normalize ad. Yalniz ekler (D-165): katalogda ayni
 * ad varsa o kayit benimsenir, degistirilmez. B52 yoksa hicbir sey yapilmaz.
 */
final class Catalog20261010Seeder extends ProtectedSeeder
{
    public const DATA_FILE = 'seeders/data/catalog_2026_10_10.php';

    /**
     * Sirket ici ornekler: tur Urun / Hizmet, kullanim yeri Sirket ici (D-190:
     * "sirket icindeki bilgisayar ayni zamanda urundur, Claude aboneligi ayni
     * zamanda bir hizmettir").
     *
     * @var list<array{kind: string, usage: string, section: string, group: string|null, name: string, unit: string|null, renewal: int|null, approval: bool}>
     */
    private const OFFICE = [
        ['kind' => 'product', 'usage' => 'office', 'section' => 'Kırtasiye', 'group' => null, 'name' => 'Kalem', 'unit' => 'Adet', 'renewal' => 3, 'approval' => false],
        ['kind' => 'product', 'usage' => 'office', 'section' => 'Kırtasiye', 'group' => null, 'name' => 'Defter', 'unit' => 'Adet', 'renewal' => 3, 'approval' => false],
        ['kind' => 'product', 'usage' => 'office', 'section' => 'Bilgisayar ve donanım', 'group' => null, 'name' => 'Bilgisayar', 'unit' => 'Adet', 'renewal' => null, 'approval' => true],
        ['kind' => 'product', 'usage' => 'office', 'section' => 'Bilgisayar ve donanım', 'group' => null, 'name' => 'Yazıcı', 'unit' => 'Adet', 'renewal' => null, 'approval' => true],
        ['kind' => 'service', 'usage' => 'office', 'section' => 'Yazılım ve abonelikler', 'group' => null, 'name' => 'Yazılım lisansı', 'unit' => 'Yıl', 'renewal' => 12, 'approval' => true],
        ['kind' => 'service', 'usage' => 'office', 'section' => 'Yazılım ve abonelikler', 'group' => null, 'name' => 'Sunucu', 'unit' => 'Yıl', 'renewal' => 12, 'approval' => true],
        ['kind' => 'service', 'usage' => 'office', 'section' => 'Yazılım ve abonelikler', 'group' => null, 'name' => 'Claude aboneliği', 'unit' => 'Ay', 'renewal' => 1, 'approval' => true],
    ];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B52A')) {
            $this->command?->warn('B52 / B52A uygulanmamis; katalog 10.10 atlandi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $created = 0;
        $adopted = 0;
        $failed = 0;

        foreach (self::rows() as $row) {
            $kind = CatalogItemKind::from($row['kind']);
            $key = 'catalog_item:'.$kind->family()[0]->value.':'.CatalogNames::normalize($row['name']);

            $this->row($key, function () use ($row, $kind, &$created, &$adopted, &$failed): ?CatalogItem {
                $existing = $this->existing($kind, $row['name']);

                if ($existing instanceof CatalogItem) {
                    $adopted++;

                    return $existing;
                }

                try {
                    /** @var CatalogItem $item */
                    $item = app(TransactionRunner::class)->run(function () use ($row, $kind): CatalogItem {
                        $service = app(CatalogItemService::class);
                        $item = $service->createFromExcel($row['name'], $kind, [
                            'section' => $row['section'],
                            'group' => $row['group'],
                            'unit' => $row['unit'],
                            'currency' => $row['currency'] ?? null,
                            'usage' => $row['usage'] ?? null,
                        ]);

                        if (array_key_exists('renewal', $row) || array_key_exists('approval', $row)) {
                            $item->forceFill([
                                'renewal_months' => $row['renewal'] ?? null,
                                'requires_approval' => (bool) ($row['approval'] ?? true),
                            ])->save();
                        }

                        return $item;
                    }, 1);
                    $created++;

                    return $item;
                } catch (CatalogItemExistsException) {
                    $adopted++;

                    return $this->existing($kind, $row['name']);
                } catch (Throwable $exception) {
                    $failed++;
                    $this->command?->warn(sprintf('Katalog kalemi "%s" yazilamadi (sonra yeniden denenecek): %s', $row['name'], $exception->getMessage()));

                    return null;
                }
            });
        }

        $this->command?->info(sprintf('Katalog 10.10: %d kalem eklendi, %d var olan benimsendi, %d satir atlandi.', $created, $adopted, $failed));
    }

    /**
     * Yazmadan: tur basina satir sayisi ve katalogda zaten bulunanlar.
     *
     * @return array<string, int>
     */
    public static function preview(): array
    {
        $counts = [];

        foreach (self::rows() as $row) {
            $counts[$row['kind']] = ($counts[$row['kind']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(): array
    {
        /** @var list<array<string, mixed>> $data */
        $data = require database_path(self::DATA_FILE);

        return [...$data, ...self::OFFICE];
    }

    private function existing(CatalogItemKind $kind, string $name): ?CatalogItem
    {
        return CatalogItem::query()
            ->whereIn('kind', array_map(static fn (CatalogItemKind $case): string => $case->value, $kind->family()))
            ->where('normalized_name', CatalogNames::normalize($name))
            ->first();
    }
}
