<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Personnel\PersonnelStatus;
use App\Enums\Personnel\ReportingRelationType;
use App\Enums\Personnel\ReportingScopeType;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\PersonnelAssignment;
use App\Models\Personnel\Position;
use App\Models\Personnel\PositionAssignment;
use App\Models\Personnel\ReportingRelationship;
use App\Services\Authorization\PositionRoleSync;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Gercek personel verisi (Konelsis_Sirket_Organizasyon_Semasi, 15 Eylul
 * 2026, kullanici talimati; telefon/e-posta listesi ve duzeltmeler de
 * 15 Eylul 2026 kullanicidan). Sistemdeki tek personel seeder'idir;
 * kurgusal ornek personel seeder'i 18 Eylul 2026'da silindi (D-105).
 *
 * Onkosul: RealOrganizationSeeder (eksik departmanlar, gorevler, unvan
 * katalogu) once calismis olmali; onceki de OrganizationStructureSeeder.
 *
 * Kullanici karari (15 Eylul 2026):
 *  - Departman ve gorev (pozisyon) personele baglanir: personnel.job_title,
 *    position_assignments ve personnel_assignments yazilir.
 *  - "Unvan" (title_id) BOS BIRAKILIR; personel kendi unvanini kendisi
 *    girecektir. Unvan katalogu (B26) hazir bekler.
 *
 * T.C. kimlik no ve ise giris tarihi hicbir kaynakta yok; gercek kisiler
 * icin uydurulmaz, bos birakilir. Atama tarihi olarak seed'in calistigi
 * gun yazilir (gercek ise giris tarihi degildir).
 */
class RealPersonnelSeeder extends Seeder
{
    public const PASSWORD = 'Konelsis.Giris.2026';

    /**
     * unit (departman kodu), position (RealOrganizationSeeder'daki gorev
     * kodu), ad, telefon (bilinmiyorsa null), e-posta, raporladigi kisinin
     * adi (null = amiri yok).
     *
     * @var list<array{unit: string, position: string, name: string, phone: ?string, email: string, manager: ?string}>
     */
    private const PEOPLE = [
        ['unit' => 'YONETIM', 'position' => 'YKB', 'name' => 'Hüseyin Güneş', 'phone' => '+90 532 445 19 30', 'email' => 'huseyin.gunes@konelsis.com', 'manager' => null],
        ['unit' => 'YONETIM', 'position' => 'YKBA', 'name' => 'Yağmur Aktaş', 'phone' => null, 'email' => 'yagmur.aktas@konelsis.com', 'manager' => 'Hüseyin Güneş'],
        ['unit' => 'YONETIM', 'position' => 'IM', 'name' => 'Mustafa Güneş', 'phone' => '+90 533 164 41 55', 'email' => 'mustafa.gunes@konelsis.com', 'manager' => 'Hüseyin Güneş'],
        ['unit' => 'ELEKTRIK', 'position' => 'EGM', 'name' => 'Murat Toksoy', 'phone' => '+90 536 446 11 46', 'email' => 'murat.toksoy@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'INSAAT', 'position' => 'INM', 'name' => 'Halil Aydın', 'phone' => '+90 535 335 19 46', 'email' => 'halil.aydin@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'INSAAT', 'position' => 'INS', 'name' => 'Doğukan Çağlan', 'phone' => '+90 553 896 06 02', 'email' => 'dogukan.caglan@konelsis.com', 'manager' => 'Halil Aydın'],
        ['unit' => 'INSAAT', 'position' => 'INS', 'name' => 'Mustafa Selim Ekşi', 'phone' => '+90 532 408 31 31', 'email' => 'mustafa.eksi@konelsis.com', 'manager' => 'Halil Aydın'],
        ['unit' => 'INSAN_KAYNAKLARI', 'position' => 'IKS', 'name' => 'Merve Uçar', 'phone' => '+90 552 203 03 37', 'email' => 'merve.ucar@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'IS_GELISTIRME', 'position' => 'TGM', 'name' => 'Yusuf Gökcan Fil', 'phone' => '+90 535 817 39 03', 'email' => 'yusuf.fil@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'IS_GELISTIRME', 'position' => 'IGM2', 'name' => 'Ersin Özdemir', 'phone' => null, 'email' => 'ersin.ozdemir@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'IS_GELISTIRME', 'position' => 'IGS', 'name' => 'Mukaddes Tekik', 'phone' => null, 'email' => 'mukaddes.tekik@konelsis.com', 'manager' => 'Ersin Özdemir'],
        ['unit' => 'IS_GELISTIRME', 'position' => 'IGS', 'name' => 'Haydar Samet Çakmak', 'phone' => '+90 553 347 97 33', 'email' => 'haydar.cakmak@konelsis.com', 'manager' => 'Ersin Özdemir'],
        ['unit' => 'PROJE', 'position' => 'PRS', 'name' => 'Eda Nur Yılmaz', 'phone' => '+90 553 885 85 56', 'email' => 'edanur.yilmaz@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'PROJE', 'position' => 'PRS', 'name' => 'Can Berk Kolukırık', 'phone' => '+90 530 064 19 96', 'email' => 'can.kolukirik@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'PROJE', 'position' => 'PRS', 'name' => 'Melih Kol', 'phone' => '+90 531 242 65 63', 'email' => 'melih.kol@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'PROJE', 'position' => 'PRS', 'name' => 'Ertuğrul Şahin', 'phone' => null, 'email' => 'ertugrul.sahin@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'YAZILIM', 'position' => 'OYM', 'name' => 'Mehmet Güneş', 'phone' => '+90 537 499 52 15', 'email' => 'mehmet.gunes@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'YAZILIM', 'position' => 'OYS', 'name' => 'Eren Güneş', 'phone' => '+90 531 347 26 85', 'email' => 'eren.gunes@konelsis.com', 'manager' => 'Mehmet Güneş'],
        ['unit' => 'BILGI_ISLEM', 'position' => 'BIS', 'name' => 'Özgür Özen', 'phone' => '+90 545 337 10 85', 'email' => 'ozgur.ozen@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'MUHASEBE', 'position' => 'MHM', 'name' => 'Nurettin Gümüş', 'phone' => '+90 533 577 05 94', 'email' => 'nurettin.gumus@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'MUHASEBE', 'position' => 'FNM', 'name' => 'Muhammetcan Canlı', 'phone' => '+90 506 090 14 96', 'email' => 'muhammetcan.canli@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'MUHASEBE', 'position' => 'MFS', 'name' => 'Melike Murat', 'phone' => '+90 555 142 44 81', 'email' => 'melike.murat@konelsis.com', 'manager' => 'Nurettin Gümüş'],
        ['unit' => 'MUHASEBE', 'position' => 'MFS', 'name' => 'Emre Özçelik', 'phone' => null, 'email' => 'emre.ozcelik@konelsis.com', 'manager' => 'Nurettin Gümüş'],
        ['unit' => 'SATIN_ALMA', 'position' => 'SAM2', 'name' => 'Engin Çetin', 'phone' => '+90 533 351 65 50', 'email' => 'engin.cetin@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'SATIN_ALMA', 'position' => 'SAS', 'name' => 'Kadir Topuz', 'phone' => null, 'email' => 'kadir.topuz@konelsis.com', 'manager' => 'Engin Çetin'],
        ['unit' => 'ATOLYE', 'position' => 'ATS', 'name' => 'Murat Şanverdi', 'phone' => null, 'email' => 'murat.sanverdi@konelsis.com', 'manager' => 'Mustafa Güneş'],
        ['unit' => 'YAZILIM', 'position' => 'YYZS', 'name' => 'Ömer Faruk Ermiş', 'phone' => '+90 553 584 62 63', 'email' => 'omer.ermis@konelsis.com', 'manager' => 'Mehmet Güneş'],
        ['unit' => 'SATIN_ALMA', 'position' => 'SAS', 'name' => 'Mehmet Secgen', 'phone' => null, 'email' => 'mehmet.secgen@konelsis.com', 'manager' => 'Engin Çetin'],
    ];

    /**
     * Departman kodu => bu departmanin org_units.manager_personnel_id'sine
     * yazilacak kisinin adi.
     *
     * @var array<string, string>
     */
    private const UNIT_MANAGERS = [
        'YONETIM' => 'Hüseyin Güneş',
        'ELEKTRIK' => 'Murat Toksoy',
        'INSAAT' => 'Halil Aydın',
        'IS_GELISTIRME' => 'Ersin Özdemir',
        'YAZILIM' => 'Mehmet Güneş',
        'MUHASEBE' => 'Nurettin Gümüş',
        'SATIN_ALMA' => 'Engin Çetin',
    ];

    public function run(): void
    {
        $units = OrgUnit::query()->get()->keyBy('code');

        if ($units->isEmpty()) {
            $this->command?->warn('Organizasyon birimleri yok; RealPersonnelSeeder atlandi (once RealOrganizationSeeder).');

            return;
        }

        $today = Carbon::now('UTC')->toDateString();

        /** @var array<string, Personnel> $people */
        $people = [];

        foreach (self::PEOPLE as $index => $row) {
            $unit = $units->get($row['unit']);
            $position = $unit !== null
                ? Position::query()->where('org_unit_id', $unit->getKey())->where('code', $row['position'])->first()
                : null;

            if ($unit === null || $position === null) {
                $this->command?->warn(sprintf('%s icin departman/gorev bulunamadi, atlandi (once RealOrganizationSeeder).', $row['name']));

                continue;
            }

            $personnel = $this->seedPersonnel($index, $row, $unit, $position);
            $people[$row['name']] = $personnel;

            $this->seedAssignment($personnel, $unit, $position, $today);
        }

        foreach (self::PEOPLE as $row) {
            $personnel = $people[$row['name']] ?? null;
            $manager = $row['manager'] !== null ? ($people[$row['manager']] ?? null) : null;

            if ($personnel !== null && $manager !== null) {
                $this->seedReporting($personnel, $manager, $today);
            }
        }

        foreach (self::UNIT_MANAGERS as $unitCode => $managerName) {
            $unit = $units->get($unitCode);
            $manager = $people[$managerName] ?? null;

            if ($unit !== null && $manager !== null && $unit->manager_personnel_id === null) {
                $unit->forceFill(['manager_personnel_id' => $manager->getKey()])->saveQuietly();
            }
        }

        $sync = app(PositionRoleSync::class);

        if ($sync->isReady()) {
            $result = $sync->syncAll();
            $this->command?->info(sprintf('Pozisyon rolleri: %d pozisyon, %d personele rol verildi.', $result['positions'], $result['granted']));
        }

        $this->command?->info(sprintf('%d gercek personel hazir (gecici parola: %s).', count($people), self::PASSWORD));
        $this->command?->info('Unvan alani bos birakildi; personel kendisi girecek.');
    }

    /**
     * @param  array{unit: string, position: string, name: string, phone: ?string, email: string, manager: ?string}  $row
     */
    private function seedPersonnel(int $index, array $row, OrgUnit $unit, Position $position): Personnel
    {
        $normalized = Personnel::normalizeEmail($row['email']);

        /** @var Personnel|null $personnel */
        $personnel = Personnel::query()->where('normalized_email', $normalized)->first();

        $attributes = [
            'full_name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'job_title' => $position->title,
            'org_unit_id' => $unit->getKey(),
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'status' => PersonnelStatus::Active,
            'personnel_no' => sprintf('KN-%04d', 1001 + $index),
        ];

        if ($personnel === null) {
            $personnel = new Personnel([
                ...$attributes,
                'password' => self::PASSWORD,
            ]);
            $personnel->forceFill(['email_verified_at' => now(), 'password_changed_at' => now()]);
            $personnel->save();

            return $personnel;
        }

        // Var olan kaydin parolasi ve durumu korunur; bos alanlar doldurulur.
        unset($attributes['status']);

        foreach ($attributes as $key => $value) {
            if ($value !== null && blank($personnel->getAttribute($key))) {
                $personnel->setAttribute($key, $value);
            }
        }

        $personnel->save();

        return $personnel;
    }

    private function seedAssignment(Personnel $personnel, OrgUnit $unit, Position $position, string $validFrom): void
    {
        PositionAssignment::query()->firstOrCreate(
            ['personnel_id' => $personnel->getKey(), 'position_id' => $position->getKey(), 'valid_until' => null],
            ['is_primary' => true, 'allocation_pct' => 100, 'valid_from' => $validFrom],
        );

        PersonnelAssignment::query()->firstOrCreate(
            ['personnel_id' => $personnel->getKey(), 'org_unit_id' => $unit->getKey(), 'effective_to' => null],
            ['job_title' => $position->title, 'effective_from' => $validFrom, 'note' => 'Gerçek organizasyon şeması (seed).'],
        );
    }

    private function seedReporting(Personnel $personnel, Personnel $manager, string $validFrom): void
    {
        $exists = ReportingRelationship::query()
            ->where('personnel_id', $personnel->getKey())
            ->where('relation_type', ReportingRelationType::Line->value)
            ->whereNull('valid_until')
            ->exists();

        if ($exists) {
            return;
        }

        $relationship = new ReportingRelationship([
            'personnel_id' => $personnel->getKey(),
            'manager_personnel_id' => $manager->getKey(),
            'relation_type' => ReportingRelationType::Line,
            'scope_type' => ReportingScopeType::All,
            'valid_from' => $validFrom,
        ]);
        $relationship->save();
    }
}
