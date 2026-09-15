<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Personnel\OrgUnitStatus;
use App\Enums\Personnel\OrgUnitType;
use App\Enums\Personnel\PositionStatus;
use App\Enums\Shared\ActiveStatus;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\PersonnelTitle;
use App\Models\Personnel\Position;
use App\Models\Reference\LegalEntity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

/**
 * Gercek sirket organizasyon semasindan (Konelsis_Sirket_Organizasyon_Semasi,
 * 15 Eylul 2026, kullanici talimati) eksik kalan departmanlar, gorevler
 * (kadro/pozisyon) ve unvanlar (B26). RealPersonnelSeeder'dan once calisir.
 *
 * Var olan OrganizationStructureSeeder'daki departmanlara dokunulmaz;
 * yalnizca semada olup sistemde olmayan dort departman eklenir. Semadaki
 * "Otomasyon ve Yazilim" ve "Muhasebe ve Finans" kutulari, var olan
 * YAZILIM ve MUHASEBE departmanlarinin gercek adlarina karsilik gelir
 * (isim degistirilmez, yalniz kod eslestirilir); "Teklif Grup Muduru"
 * semada ayri departman degil Is Gelistirme icinde bir gorevdir, bu yuzden
 * var olan ayri TEKLIF departmani bu seed'de kullanilmaz.
 *
 * "Gorev" (Position.title / personnel.job_title) departmana baglidir;
 * "unvan" (personnel_titles, B26) departmandan bagimsiz tekrar eden
 * kademe adidir (Sorumlu, Mudur, Grup Muduru...). Ikisi kasitli ayridir.
 */
class RealOrganizationSeeder extends Seeder
{
    /**
     * @var list<array{code: string, name: string}>
     */
    private const NEW_DEPARTMENTS = [
        ['code' => 'ELEKTRIK', 'name' => 'Elektrik'],
        ['code' => 'INSAAT', 'name' => 'İnşaat'],
        ['code' => 'BILGI_ISLEM', 'name' => 'Bilgi İşlem'],
        ['code' => 'ATOLYE', 'name' => 'Atölye'],
    ];

    /**
     * Unvan katalogu (B26): kod, ad, kademe (yuksek = daha kidemli).
     *
     * @var list<array{code: string, name: string, rank: int}>
     */
    private const TITLES = [
        ['code' => 'YK_BASKANI', 'name' => 'Yönetim kurulu başkanı', 'rank' => 100],
        ['code' => 'IDARI_MUDUR', 'name' => 'İdari müdür', 'rank' => 90],
        ['code' => 'GRUP_MUDURU', 'name' => 'Grup müdürü', 'rank' => 70],
        ['code' => 'MUDUR', 'name' => 'Müdür', 'rank' => 60],
        ['code' => 'SORUMLU', 'name' => 'Sorumlu', 'rank' => 30],
        ['code' => 'ASISTAN', 'name' => 'Asistan', 'rank' => 20],
    ];

    /**
     * Departman koduna gore gorev (kadro) listesi: kod, baslik, kademe
     * (0 = birey, 2 = departman yoneticisi, 3 = ust yonetim), kadro sayisi.
     *
     * Baslik metni cumle bicimi (yalniz ilk harf buyuk) - kullanici duzeltmesi,
     * 15 Eylul 2026: "Otomasyon yazilim sorumlusu, proje sorumlusu, finans
     * muduru, satin alma sorumlusu gibi olmali."
     *
     * @var array<string, array<int, array{code: string, title: string, level: int, headcount: int}>>
     */
    private const POSITIONS = [
        'YONETIM' => [
            ['code' => 'YKB', 'title' => 'Yönetim kurulu başkanı', 'level' => 3, 'headcount' => 1],
            ['code' => 'YKBA', 'title' => 'Yönetim kurulu başkanı asistanı', 'level' => 0, 'headcount' => 1],
            ['code' => 'IM', 'title' => 'İdari müdür', 'level' => 3, 'headcount' => 1],
        ],
        'ELEKTRIK' => [
            ['code' => 'EGM', 'title' => 'Elektrik grup müdürü', 'level' => 2, 'headcount' => 1],
        ],
        'INSAAT' => [
            ['code' => 'INM', 'title' => 'İnşaat müdürü', 'level' => 2, 'headcount' => 1],
            ['code' => 'INS', 'title' => 'İnşaat sorumlusu', 'level' => 0, 'headcount' => 2],
        ],
        'INSAN_KAYNAKLARI' => [
            ['code' => 'IKS', 'title' => 'İnsan kaynakları sorumlusu', 'level' => 0, 'headcount' => 1],
        ],
        'IS_GELISTIRME' => [
            ['code' => 'TGM', 'title' => 'Teklif grup müdürü', 'level' => 2, 'headcount' => 1],
            ['code' => 'IGM2', 'title' => 'İş geliştirme müdürü', 'level' => 2, 'headcount' => 1],
            ['code' => 'IGS', 'title' => 'İş geliştirme sorumlusu', 'level' => 0, 'headcount' => 2],
        ],
        'PROJE' => [
            ['code' => 'PRS', 'title' => 'Proje sorumlusu', 'level' => 0, 'headcount' => 4],
        ],
        'YAZILIM' => [
            ['code' => 'OYM', 'title' => 'Otomasyon yazılım müdürü', 'level' => 2, 'headcount' => 1],
            ['code' => 'OYS', 'title' => 'Otomasyon yazılım sorumlusu', 'level' => 0, 'headcount' => 1],
            ['code' => 'YYZS', 'title' => 'Yazılım ve yapay zeka sorumlusu', 'level' => 0, 'headcount' => 1],
        ],
        'BILGI_ISLEM' => [
            ['code' => 'BIS', 'title' => 'Bilgi işlem sorumlusu', 'level' => 0, 'headcount' => 1],
        ],
        'MUHASEBE' => [
            ['code' => 'MHM', 'title' => 'Muhasebe müdürü', 'level' => 2, 'headcount' => 1],
            ['code' => 'FNM', 'title' => 'Finans müdürü', 'level' => 2, 'headcount' => 1],
            ['code' => 'MFS', 'title' => 'Muhasebe / finans sorumlusu', 'level' => 0, 'headcount' => 2],
        ],
        'SATIN_ALMA' => [
            ['code' => 'SAM2', 'title' => 'Satın alma müdürü', 'level' => 2, 'headcount' => 1],
            ['code' => 'SAS', 'title' => 'Satın alma sorumlusu', 'level' => 0, 'headcount' => 2],
        ],
        'ATOLYE' => [
            ['code' => 'ATS', 'title' => 'Atölye sorumlusu', 'level' => 0, 'headcount' => 1],
        ],
    ];

    public function run(): void
    {
        $legalEntityId = LegalEntity::query()
            ->where('code', (string) config('konelsis.legal_entity.code', 'KONELSIS_MAIN'))
            ->value('id') ?? LegalEntity::query()->value('id');

        foreach (self::NEW_DEPARTMENTS as ['code' => $code, 'name' => $name]) {
            OrgUnit::query()->firstOrCreate(
                ['code' => $code],
                [
                    'legal_entity_id' => $legalEntityId,
                    'name' => $name,
                    'unit_type' => OrgUnitType::Department,
                    'status' => OrgUnitStatus::Active,
                    'valid_from' => Carbon::now('UTC')->toDateString(),
                ],
            );
        }

        if (Schema::hasTable('personnel_titles')) {
            foreach (self::TITLES as $row) {
                PersonnelTitle::query()->firstOrCreate(
                    ['code' => $row['code']],
                    ['name' => $row['name'], 'rank_level' => $row['rank'], 'status' => ActiveStatus::Active],
                );
            }
        } else {
            $this->command?->warn('personnel_titles tablosu yok; B26 migration uygulanmadan unvan katalogu atlandi.');
        }

        $units = OrgUnit::query()->get()->keyBy('code');

        foreach (self::POSITIONS as $unitCode => $rows) {
            $unit = $units->get($unitCode);

            if ($unit === null) {
                $this->command?->warn(sprintf('%s departmani yok; gorevleri atlandi.', $unitCode));

                continue;
            }

            foreach ($rows as $row) {
                Position::query()->firstOrCreate(
                    ['org_unit_id' => $unit->getKey(), 'code' => $row['code']],
                    [
                        'title' => $row['title'],
                        'managerial_level' => $row['level'],
                        'headcount' => $row['headcount'],
                        'status' => PositionStatus::Active,
                        'valid_from' => '2016-01-01',
                    ],
                );
            }
        }

        $this->command?->info('RealOrganizationSeeder: eksik departmanlar, gorevler ve unvan katalogu hazir.');
    }
}
