<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Acquisition\ProjectReference;
use App\Services\Acquisition\ProjectReferenceService;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\TransactionRunner;
use Database\Seeders\Support\ProtectedSeeder;
use Throwable;

/**
 * Referans listesi, 08.10.2026 (D-177, 8 Ekim 2026 kullanici talimati:
 * "Excel'de tum referanslarimizi zaten ekledik. Sen tum listeye gore seed
 * dosyasini olustur").
 *
 * Veri: database/seeders/data/references_2026_10_08.php (bes Excel dosyasindan
 * uretildi: GES 166, HES 56, RES 22, OTOMASYON 162, TM+ENH 23 = 429 satir).
 * Metin ve sira Excel'deki gibidir; tipler dosyadan gelir (OTOMASYON ->
 * Otomasyon / Process, TM+ENH -> TM ve ENH/EIH), metninde BESS, ENERJI
 * DEPOLAMA ya da EDT gecen satira BESS de eklenir.
 *
 * Her satir bir referanstir; satir anahtari dosya + sira no ("reference:ges:12";
 * sira nosu bos satir onceki nonun "a" ekiyle). Yalniz ekler (D-165): islenmis
 * satir seed arsivinde oldugu icin tekrar yazilmaz, canlida silinen ya da
 * duzenlenen referans geri gelmez. Yazma ProjectReferenceService::create ile,
 * Personel Hareketleri'ne "Sistem" olarak duser; satir kendi transaction'indadir,
 * hata verirse yalniz o satir geri alinir ve sonraki kurulumda yeniden denenir.
 * B50 uygulanmadiysa hicbir sey yapilmaz. preview() hicbir sey yazmaz.
 */
final class References20261008Seeder extends ProtectedSeeder
{
    public const DATA_FILE = 'seeders/data/references_2026_10_08.php';

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B50')) {
            $this->command?->warn('B50 uygulanmamis; referans listesi 08.10 atlandi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $created = 0;
        $skipped = 0;

        foreach (self::rows() as $row) {
            $this->row('reference:'.$row['key'], function () use ($row, &$created, &$skipped): ?ProjectReference {
                try {
                    /** @var ProjectReference $reference */
                    $reference = app(TransactionRunner::class)->run(fn (): ProjectReference => app(ProjectReferenceService::class)->create([
                        'title' => $row['title'],
                        'sort_order' => $row['sort_order'],
                        'scope_types' => $row['types'],
                    ]), 1);
                    $created++;

                    return $reference;
                } catch (Throwable $exception) {
                    $skipped++;
                    $this->command?->warn(sprintf('Referans "%s" yazilamadi (sonra yeniden denenecek): %s', $row['key'], $exception->getMessage()));

                    return null;
                }
            });
        }

        $this->command?->info(sprintf('Referans listesi 08.10: %d referans eklendi, %d satir atlandi.', $created, $skipped));
    }

    /**
     * Yazmadan: satir sayisi, tip basina sayi, daha once islenmis satirlar ve
     * ayni metinle zaten kayitli referanslar (bilgi icin; seed yine ekler).
     *
     * @return array{rows: int, by_type: array<string, int>, already_seeded: int, to_create: int, same_title_in_db: int, keyless_number: list<string>, bess: list<string>}
     */
    public function preview(): array
    {
        $rows = self::rows();
        $byType = [];
        $already = 0;
        $sameTitle = 0;
        $keyless = [];
        $bess = [];
        $existing = SchemaReadiness::hasBatch('B50')
            ? array_flip(ProjectReference::query()->pluck('title')->map(static fn (mixed $title): string => (string) $title)->all())
            : [];

        foreach ($rows as $row) {
            foreach ($row['types'] as $type) {
                $byType[$type] = ($byType[$type] ?? 0) + 1;
            }

            if (SchemaReadiness::hasBatch('B45') && $this->isArchived('reference:'.$row['key'])) {
                $already++;
            }

            if (isset($existing[$row['title']])) {
                $sameTitle++;
            }

            if ($row['no'] === null) {
                $keyless[] = $row['key'].' '.$row['title'];
            }

            if (in_array('bes', $row['types'], true)) {
                $bess[] = $row['key'].' '.$row['title'];
            }
        }

        return [
            'rows' => count($rows),
            'by_type' => $byType,
            'already_seeded' => $already,
            'to_create' => count($rows) - $already,
            'same_title_in_db' => $sameTitle,
            'keyless_number' => $keyless,
            'bess' => $bess,
        ];
    }

    /**
     * @return list<array{key: string, file: string, no: int|null, sort_order: int, title: string, types: list<string>}>
     */
    public static function rows(): array
    {
        /** @var list<array{key: string, file: string, no: int|null, sort_order: int, title: string, types: list<string>}> $rows */
        $rows = require database_path(self::DATA_FILE);

        return $rows;
    }
}
