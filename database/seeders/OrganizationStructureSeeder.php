<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Personnel\CompetencyCategory;
use App\Enums\Personnel\OrgUnitStatus;
use App\Enums\Personnel\OrgUnitType;
use App\Enums\Shared\ActiveStatus;
use App\Models\Personnel\Competency;
use App\Models\Personnel\OrgUnit;
use App\Models\Reference\LegalEntity;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Support\Carbon;

/**
 * Sirket yapisina gore organizasyon birimi ve yetkinlik baslangic listesi
 * (docs/planning/00 §4 ve §7). Gercek liste onaylandikca guncellenir.
 *
 * Onceki surumde bu birimler departments tablosundaydi; D-62 ile ayni
 * kod/adla org_units'e tasindi (unit_type=department).
 *
 * D-165: yalniz eksik birim/yetkinlik eklenir; var olan kayit guncellenmez.
 * Islenen her satir seed arsivine duser ve bir daha islenmez.
 */
class OrganizationStructureSeeder extends ProtectedSeeder
{
    public function run(): void
    {
        $legalEntityId = LegalEntity::query()
            ->where('code', (string) config('konelsis.legal_entity.code', 'KONELSIS_MAIN'))
            ->value('id') ?? LegalEntity::query()->value('id');

        $orgUnits = [
            ['YONETIM', 'Yönetim'],
            ['IS_GELISTIRME', 'İş Geliştirme'],
            ['TEKLIF', 'Teklif'],
            ['PROJE', 'Proje'],
            ['SATIN_ALMA', 'Satın Alma'],
            ['MUHASEBE', 'Muhasebe'],
            ['YAZILIM', 'Yazılım'],
            ['LOJISTIK', 'Lojistik'],
            ['SAHA', 'Saha'],
            ['INSAN_KAYNAKLARI', 'İnsan Kaynakları'],
        ];

        foreach ($orgUnits as [$code, $name]) {
            $this->row('org-unit:'.$code, static fn (): OrgUnit => OrgUnit::query()->firstOrCreate(
                ['code' => $code],
                [
                    'legal_entity_id' => $legalEntityId,
                    'name' => $name,
                    'unit_type' => OrgUnitType::Department,
                    'status' => OrgUnitStatus::Active,
                    'valid_from' => Carbon::now('UTC')->toDateString(),
                ],
            ));
        }

        $competencies = [
            ['YG', 'Yüksek gerilim', CompetencyCategory::Electrical],
            ['AG', 'Alçak gerilim', CompetencyCategory::Electrical],
            ['TRAFO', 'Trafo ve şalt', CompetencyCategory::Electrical],
            ['SCADA', 'SCADA', CompetencyCategory::Automation],
            ['PLC', 'PLC programlama', CompetencyCategory::Automation],
            ['EMS', 'Enerji yönetim sistemi', CompetencyCategory::Automation],
            ['GES', 'Güneş enerjisi santrali', CompetencyCategory::Field],
            ['RES', 'Rüzgâr enerjisi santrali', CompetencyCategory::Field],
            ['HES', 'Hidroelektrik santral', CompetencyCategory::Field],
            ['BESS', 'Batarya depolama', CompetencyCategory::Field],
            ['TEST', 'Test ve devreye alma', CompetencyCategory::Field],
            ['PROJE_YONETIMI', 'Proje yönetimi', CompetencyCategory::Management],
            ['SOZLESME', 'Sözleşme ve teklif', CompetencyCategory::Commercial],
            ['ISG', 'İş sağlığı ve güvenliği', CompetencyCategory::Safety],
            ['AUTOCAD', 'AutoCAD', CompetencyCategory::Software],
            ['MS_PROJECT', 'MS Project', CompetencyCategory::Software],
            ['INGILIZCE', 'İngilizce', CompetencyCategory::Language],
        ];

        foreach ($competencies as [$code, $name, $category]) {
            $this->row('competency:'.$code, static fn (): Competency => Competency::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => $category, 'status' => ActiveStatus::Active],
            ));
        }
    }
}
