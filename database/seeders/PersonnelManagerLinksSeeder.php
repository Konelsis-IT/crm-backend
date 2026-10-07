<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Personnel\ReportingRelationType;
use App\Enums\Personnel\ReportingScopeType;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\ReportingRelationship;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Support\Carbon;

/**
 * Birden fazla yoneticili personel (D-167, 6 Ekim 2026 kullanici talimati:
 * "Mukaddes ve Haydar personelleri is gelistirme ve teklif departmanina bagli,
 * ayni anda birden fazla mudure baglidir. Yusuf bey ve Ersin bey'in ikisinde
 * de ilgili personellerin raporlari gorulmelidir").
 *
 * Her personel - yonetici cifti bir satirdir: o cift icin acik bir bag yoksa
 * eklenir (Ersin Ozdemir hat yoneticisi, Yusuf Gokcan Fil islevsel yonetici);
 * var olan baga dokunulmaz. Rapor gorunurlugu acik her yonetici bagini esit
 * sayar (D-121). Kurulumda DatabaseSeeder::DEPLOY_SEEDERS ile calisir.
 */
final class PersonnelManagerLinksSeeder extends ProtectedSeeder
{
    /** @var list<array{personnel: string, manager: string, type: ReportingRelationType}> */
    private const LINKS = [
        ['personnel' => 'mukaddes.tekik@konelsis.com', 'manager' => 'ersin.ozdemir@konelsis.com', 'type' => ReportingRelationType::Line],
        ['personnel' => 'mukaddes.tekik@konelsis.com', 'manager' => 'yusuf.fil@konelsis.com', 'type' => ReportingRelationType::Functional],
        ['personnel' => 'haydar.cakmak@konelsis.com', 'manager' => 'ersin.ozdemir@konelsis.com', 'type' => ReportingRelationType::Line],
        ['personnel' => 'haydar.cakmak@konelsis.com', 'manager' => 'yusuf.fil@konelsis.com', 'type' => ReportingRelationType::Functional],
    ];

    public function run(): void
    {
        $added = 0;

        foreach (self::LINKS as $link) {
            $this->row('manager:'.$link['personnel'].'|'.$link['manager'], function () use ($link, &$added): ReportingRelationship|bool|null {
                $personnel = Personnel::query()->where('normalized_email', Personnel::normalizeEmail($link['personnel']))->first();
                $manager = Personnel::query()->where('normalized_email', Personnel::normalizeEmail($link['manager']))->first();

                if ($personnel === null || $manager === null) {
                    $this->command?->warn(sprintf('%s ya da %s bulunamadi; yonetici bagi sonra yeniden denenecek.', $link['personnel'], $link['manager']));

                    return null;
                }

                $exists = ReportingRelationship::query()
                    ->where('personnel_id', $personnel->getKey())
                    ->where('manager_personnel_id', $manager->getKey())
                    ->whereNull('valid_until')
                    ->exists();

                if ($exists) {
                    return true;
                }

                // Kisinin tek acik hat yoneticisi olabilir: canlida baskasi hat
                // yoneticisiyse bu bag islevsel olarak eklenir (var olana dokunulmaz).
                $type = $link['type'];

                if ($type === ReportingRelationType::Line && ReportingRelationship::query()
                    ->where('personnel_id', $personnel->getKey())
                    ->where('relation_type', ReportingRelationType::Line->value)
                    ->whereNull('valid_until')
                    ->exists()) {
                    $type = ReportingRelationType::Functional;
                }

                $relationship = new ReportingRelationship([
                    'personnel_id' => $personnel->getKey(),
                    'manager_personnel_id' => $manager->getKey(),
                    'relation_type' => $type,
                    'scope_type' => ReportingScopeType::All,
                    'valid_from' => Carbon::now('UTC')->toDateString(),
                ]);
                $relationship->save();
                $added++;

                return $relationship;
            });
        }

        $this->command?->info(sprintf('Yonetici baglari: %d yeni bag eklendi (Mukaddes Tekik, Haydar Samet Cakmak).', $added));
    }
}
