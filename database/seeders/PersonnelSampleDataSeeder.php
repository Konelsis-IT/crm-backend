<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Personnel\CertificationStatus;
use App\Enums\Personnel\CompetencyLevel;
use App\Enums\Personnel\PersonnelStatus;
use App\Enums\Personnel\PositionStatus;
use App\Enums\Personnel\ReportingRelationType;
use App\Enums\Personnel\ReportingScopeType;
use App\Enums\Personnel\TrainingAttendanceOutcome;
use App\Enums\Personnel\TrainingKind;
use App\Enums\Personnel\TrainingStatus;
use App\Enums\Shared\ActiveStatus;
use App\Models\Personnel\Certification;
use App\Models\Personnel\Competency;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\PersonnelAssignment;
use App\Models\Personnel\PersonnelCertification;
use App\Models\Personnel\PersonnelCompetency;
use App\Models\Personnel\Position;
use App\Models\Personnel\PositionAssignment;
use App\Models\Personnel\ReportingRelationship;
use App\Models\Personnel\Training;
use App\Models\Personnel\TrainingAttendance;
use App\Services\Authorization\PositionRoleSync;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ornek personel verisi (kullanici istegi, 11 Eylul 2026): farkli departman
 * ve pozisyonlarda 15 kisi; tum alanlar dolu (kimlik no, telefon, gorev,
 * fotograf, ise giris, pozisyon atamasi, amir, yetkinlikler, sertifikalar,
 * egitimler). Her calistirmada ayni kisiler korunur (e-posta anahtar);
 * mevcut kayitlarin parolasi degistirilmez.
 *
 * Giris: tum ornek personel icin parola "Konelsis.2026". Departman
 * yoneticileri birim yoneticisi olarak atanir ve Genel Mudur'e (bootstrap
 * yonetici) raporlar; digerleri kendi departman yoneticisine raporlar.
 * Pozisyon rolleri (D-81) B03A uygulanmissa PositionRoleSync ile verilir.
 */
class PersonnelSampleDataSeeder extends Seeder
{
    public const PASSWORD = 'Konelsis.2026';

    public const PHOTO_DIRECTORY = 'personel-fotograflari';

    /**
     * @var array<string, array<int, array{code: string, title: string, level: int, grade: string, headcount: int}>>
     */
    private const POSITIONS = [
        'IS_GELISTIRME' => [
            ['code' => 'IGM', 'title' => 'İş Geliştirme Müdürü', 'level' => 2, 'grade' => 'M2', 'headcount' => 1],
            ['code' => 'IGU', 'title' => 'İş Geliştirme Uzmanı', 'level' => 0, 'grade' => 'U2', 'headcount' => 2],
        ],
        'TEKLIF' => [
            ['code' => 'TKM', 'title' => 'Teklif Müdürü', 'level' => 2, 'grade' => 'M2', 'headcount' => 1],
            ['code' => 'TKMH', 'title' => 'Teklif Mühendisi', 'level' => 0, 'grade' => 'U2', 'headcount' => 2],
        ],
        'PROJE' => [
            ['code' => 'PM', 'title' => 'Proje Müdürü', 'level' => 2, 'grade' => 'M2', 'headcount' => 1],
            ['code' => 'PMH', 'title' => 'Proje Mühendisi', 'level' => 0, 'grade' => 'U2', 'headcount' => 3],
            ['code' => 'PKU', 'title' => 'Proje Kontrol Uzmanı', 'level' => 0, 'grade' => 'U1', 'headcount' => 1],
        ],
        'SATIN_ALMA' => [
            ['code' => 'SAM', 'title' => 'Satın Alma Müdürü', 'level' => 2, 'grade' => 'M2', 'headcount' => 1],
            ['code' => 'SAU', 'title' => 'Satın Alma Uzmanı', 'level' => 0, 'grade' => 'U2', 'headcount' => 2],
        ],
        'MUHASEBE' => [
            ['code' => 'MHU', 'title' => 'Muhasebe Uzmanı', 'level' => 0, 'grade' => 'U2', 'headcount' => 1],
        ],
        'YAZILIM' => [
            ['code' => 'YZM', 'title' => 'Yazılım ve Otomasyon Müdürü', 'level' => 2, 'grade' => 'M2', 'headcount' => 1],
            ['code' => 'OTM', 'title' => 'Otomasyon Mühendisi', 'level' => 0, 'grade' => 'U2', 'headcount' => 2],
        ],
        'LOJISTIK' => [
            ['code' => 'LJU', 'title' => 'Lojistik Uzmanı', 'level' => 0, 'grade' => 'U1', 'headcount' => 1],
        ],
        'SAHA' => [
            ['code' => 'SHS', 'title' => 'Saha Şefi', 'level' => 1, 'grade' => 'M1', 'headcount' => 1],
        ],
        'INSAN_KAYNAKLARI' => [
            ['code' => 'IKU', 'title' => 'İnsan Kaynakları Uzmanı', 'level' => 0, 'grade' => 'U2', 'headcount' => 1],
        ],
    ];

    /**
     * unit, pozisyon kodu, ad, e-posta, ise giris, yetkinlikler (kod => seviye), sertifikalar (kod => [belge no, verilis])
     *
     * @var list<array{unit: string, position: string, name: string, email: string, hired: string, competencies: array<string, string>, certifications: array<string, array{0: string, 1: string}>}>
     */
    private const PEOPLE = [
        ['unit' => 'IS_GELISTIRME', 'position' => 'IGM', 'name' => 'Ayşe Demir', 'email' => 'ayse.demir@konelsis.com.tr', 'hired' => '2019-03-04', 'competencies' => ['SOZLESME' => 'expert', 'PROJE_YONETIMI' => 'advanced', 'INGILIZCE' => 'advanced'], 'certifications' => ['PMP' => ['PMP-3341021', '2023-06-15']]],
        ['unit' => 'IS_GELISTIRME', 'position' => 'IGU', 'name' => 'Burak Yıldız', 'email' => 'burak.yildiz@konelsis.com.tr', 'hired' => '2022-09-12', 'competencies' => ['SOZLESME' => 'intermediate', 'GES' => 'intermediate', 'INGILIZCE' => 'intermediate'], 'certifications' => ['ILK_YARDIM' => ['IY-2024-0871', '2024-02-20']]],
        ['unit' => 'TEKLIF', 'position' => 'TKM', 'name' => 'Elif Şahin', 'email' => 'elif.sahin@konelsis.com.tr', 'hired' => '2018-01-15', 'competencies' => ['SOZLESME' => 'expert', 'GES' => 'advanced', 'AUTOCAD' => 'intermediate', 'INGILIZCE' => 'advanced'], 'certifications' => ['PMP' => ['PMP-2987114', '2022-11-02']]],
        ['unit' => 'TEKLIF', 'position' => 'TKMH', 'name' => 'Mert Kaya', 'email' => 'mert.kaya@konelsis.com.tr', 'hired' => '2023-04-03', 'competencies' => ['AG' => 'advanced', 'AUTOCAD' => 'advanced', 'GES' => 'intermediate'], 'certifications' => ['YG_ISLETME' => ['EMO-YG-77120', '2023-09-10']]],
        ['unit' => 'PROJE', 'position' => 'PM', 'name' => 'Zeynep Arslan', 'email' => 'zeynep.arslan@konelsis.com.tr', 'hired' => '2017-06-01', 'competencies' => ['PROJE_YONETIMI' => 'expert', 'MS_PROJECT' => 'expert', 'GES' => 'advanced', 'RES' => 'intermediate'], 'certifications' => ['PMP' => ['PMP-2551903', '2021-05-18'], 'ISG_UZMAN' => ['ISG-B-4410', '2022-03-01']]],
        ['unit' => 'PROJE', 'position' => 'PMH', 'name' => 'Can Öztürk', 'email' => 'can.ozturk@konelsis.com.tr', 'hired' => '2021-02-08', 'competencies' => ['YG' => 'advanced', 'TRAFO' => 'advanced', 'TEST' => 'intermediate', 'MS_PROJECT' => 'intermediate'], 'certifications' => ['YG_ISLETME' => ['EMO-YG-65211', '2022-06-14'], 'ILK_YARDIM' => ['IY-2023-1120', '2023-08-09']]],
        ['unit' => 'PROJE', 'position' => 'PMH', 'name' => 'Selin Çelik', 'email' => 'selin.celik@konelsis.com.tr', 'hired' => '2022-11-21', 'competencies' => ['GES' => 'advanced', 'BESS' => 'intermediate', 'AUTOCAD' => 'advanced'], 'certifications' => ['ILK_YARDIM' => ['IY-2024-0332', '2024-01-11']]],
        ['unit' => 'PROJE', 'position' => 'PKU', 'name' => 'Emre Doğan', 'email' => 'emre.dogan@konelsis.com.tr', 'hired' => '2020-10-05', 'competencies' => ['MS_PROJECT' => 'expert', 'PROJE_YONETIMI' => 'advanced', 'INGILIZCE' => 'intermediate'], 'certifications' => []],
        ['unit' => 'SATIN_ALMA', 'position' => 'SAM', 'name' => 'Deniz Aydın', 'email' => 'deniz.aydin@konelsis.com.tr', 'hired' => '2016-09-19', 'competencies' => ['SOZLESME' => 'advanced', 'INGILIZCE' => 'advanced', 'TRAFO' => 'beginner'], 'certifications' => []],
        ['unit' => 'SATIN_ALMA', 'position' => 'SAU', 'name' => 'Gamze Koç', 'email' => 'gamze.koc@konelsis.com.tr', 'hired' => '2023-07-17', 'competencies' => ['SOZLESME' => 'intermediate', 'INGILIZCE' => 'intermediate'], 'certifications' => []],
        ['unit' => 'MUHASEBE', 'position' => 'MHU', 'name' => 'Hakan Kurt', 'email' => 'hakan.kurt@konelsis.com.tr', 'hired' => '2019-11-11', 'competencies' => ['SOZLESME' => 'intermediate'], 'certifications' => []],
        ['unit' => 'YAZILIM', 'position' => 'YZM', 'name' => 'Merve Aksoy', 'email' => 'merve.aksoy@konelsis.com.tr', 'hired' => '2018-08-27', 'competencies' => ['SCADA' => 'expert', 'PLC' => 'expert', 'EMS' => 'advanced', 'INGILIZCE' => 'advanced'], 'certifications' => ['YG_ISLETME' => ['EMO-YG-58007', '2021-10-25']]],
        ['unit' => 'YAZILIM', 'position' => 'OTM', 'name' => 'Onur Polat', 'email' => 'onur.polat@konelsis.com.tr', 'hired' => '2022-03-14', 'competencies' => ['PLC' => 'advanced', 'SCADA' => 'intermediate', 'EMS' => 'intermediate'], 'certifications' => ['YUKSEKTE_CALISMA' => ['MYK-YC-90211', '2024-04-02']]],
        ['unit' => 'LOJISTIK', 'position' => 'LJU', 'name' => 'Fatma Yılmaz', 'email' => 'fatma.yilmaz@konelsis.com.tr', 'hired' => '2021-05-24', 'competencies' => ['SOZLESME' => 'beginner', 'INGILIZCE' => 'intermediate'], 'certifications' => ['ILK_YARDIM' => ['IY-2022-0456', '2022-05-30']]],
        ['unit' => 'SAHA', 'position' => 'SHS', 'name' => 'Murat Aslan', 'email' => 'murat.aslan@konelsis.com.tr', 'hired' => '2015-04-06', 'competencies' => ['GES' => 'expert', 'TEST' => 'expert', 'ISG' => 'advanced', 'AG' => 'advanced'], 'certifications' => ['ISG_UZMAN' => ['ISG-C-1187', '2020-09-01'], 'YUKSEKTE_CALISMA' => ['MYK-YC-44810', '2023-02-13'], 'ILK_YARDIM' => ['IY-2023-0099', '2023-03-06']]],
        ['unit' => 'INSAN_KAYNAKLARI', 'position' => 'IKU', 'name' => 'Nazlı Erdem', 'email' => 'nazli.erdem@konelsis.com.tr', 'hired' => '2020-01-20', 'competencies' => ['INGILIZCE' => 'advanced', 'ISG' => 'intermediate'], 'certifications' => ['ISG_UZMAN' => ['ISG-C-2260', '2021-07-19']]],
    ];

    /**
     * @var array<string, array{name: string, issuer: string, months: int, field: bool}>
     */
    private const CERTIFICATIONS = [
        'ISG_UZMAN' => ['name' => 'İş Güvenliği Uzmanlığı', 'issuer' => 'Çalışma ve Sosyal Güvenlik Bakanlığı', 'months' => 60, 'field' => true],
        'ILK_YARDIM' => ['name' => 'Temel İlk Yardım', 'issuer' => 'Sağlık Bakanlığı', 'months' => 36, 'field' => true],
        'YG_ISLETME' => ['name' => 'Yüksek Gerilim İşletme Sorumluluğu', 'issuer' => 'Elektrik Mühendisleri Odası', 'months' => 60, 'field' => true],
        'PMP' => ['name' => 'Project Management Professional (PMP)', 'issuer' => 'Project Management Institute', 'months' => 36, 'field' => false],
        'YUKSEKTE_CALISMA' => ['name' => 'Yüksekte Çalışma Eğitimi', 'issuer' => 'Mesleki Yeterlilik Kurumu', 'months' => 24, 'field' => true],
    ];

    /**
     * @var array<string, array{name: string, kind: string, provider: string, planned: string, hours: int, status: string, attendees: list<string>}>
     */
    private const TRAININGS = [
        'ISG-TEMEL-2026' => ['name' => 'Temel İş Sağlığı ve Güvenliği Eğitimi', 'kind' => 'internal', 'provider' => 'Konelsis İSG Birimi', 'planned' => '2026-03-12', 'hours' => 8, 'status' => 'completed', 'attendees' => ['can.ozturk', 'selin.celik', 'murat.aslan', 'onur.polat', 'mert.kaya', 'fatma.yilmaz']],
        'SCADA-ILERI-2026' => ['name' => 'İleri SCADA ve Enerji Yönetim Sistemleri', 'kind' => 'external', 'provider' => 'Siemens Eğitim Merkezi', 'planned' => '2026-05-20', 'hours' => 16, 'status' => 'completed', 'attendees' => ['merve.aksoy', 'onur.polat', 'can.ozturk']],
        'PLC-2026' => ['name' => 'PLC Programlama (TIA Portal)', 'kind' => 'online', 'provider' => 'Konelsis Akademi', 'planned' => '2026-10-15', 'hours' => 12, 'status' => 'planned', 'attendees' => ['onur.polat', 'selin.celik', 'mert.kaya']],
    ];

    public function run(): void
    {
        $units = OrgUnit::query()->get()->keyBy('code');

        if ($units->isEmpty()) {
            $this->command?->warn('Organizasyon birimleri yok; PersonnelSampleDataSeeder atlandi (once OrganizationStructureSeeder).');

            return;
        }

        $positions = $this->seedPositions($units);
        $competencies = Competency::query()->get()->keyBy('code');
        $certifications = $this->seedCertifications();
        $executive = $this->executive();

        /** @var array<string, Personnel> $people */
        $people = [];
        /** @var array<string, Personnel> $unitManagers */
        $unitManagers = [];

        foreach (self::PEOPLE as $index => $row) {
            $unit = $units->get($row['unit']);
            $position = $positions[$row['unit'].'/'.$row['position']] ?? null;

            if ($unit === null || $position === null) {
                continue;
            }

            $personnel = $this->seedPersonnel($index, $row, $unit, $position);
            $people[$row['email']] = $personnel;

            $this->seedAssignment($personnel, $unit, $position, $row['hired']);
            $this->seedCompetencies($personnel, $row['competencies'], $competencies);
            $this->seedCertificationsFor($personnel, $row['certifications'], $certifications, $executive);

            if ((int) $position->managerial_level >= 2 && ! isset($unitManagers[$row['unit']])) {
                $unitManagers[$row['unit']] = $personnel;
            }
        }

        // Birim yoneticileri ve amirlik zinciri.
        foreach ($unitManagers as $unitCode => $manager) {
            $unit = $units->get($unitCode);

            if ($unit !== null && $unit->manager_personnel_id === null) {
                $unit->forceFill(['manager_personnel_id' => $manager->getKey()])->saveQuietly();
            }
        }

        $management = $units->get('YONETIM');

        if ($management !== null && $management->manager_personnel_id === null && $executive !== null) {
            $management->forceFill(['manager_personnel_id' => $executive->getKey()])->saveQuietly();
        }

        foreach (self::PEOPLE as $row) {
            $personnel = $people[$row['email']] ?? null;

            if ($personnel === null) {
                continue;
            }

            $manager = $unitManagers[$row['unit']] ?? null;

            if ($manager !== null && $manager->is($personnel)) {
                $manager = $executive;
            }

            $manager ??= $executive;

            if ($manager !== null && ! $manager->is($personnel)) {
                $this->seedReporting($personnel, $manager, $row['hired']);
            }
        }

        $this->seedTrainings($people);

        $sync = app(PositionRoleSync::class);

        if ($sync->isReady()) {
            $result = $sync->syncAll();
            $this->command?->info(sprintf('Pozisyon rolleri: %d pozisyon, %d personele rol verildi.', $result['positions'], $result['granted']));
        }

        $this->command?->info(sprintf('%d ornek personel hazir (parola: %s).', count($people), self::PASSWORD));
    }

    /**
     * @param  \Illuminate\Support\Collection<string, OrgUnit>  $units
     * @return array<string, Position>
     */
    private function seedPositions($units): array
    {
        $positions = [];

        foreach (self::POSITIONS as $unitCode => $rows) {
            $unit = $units->get($unitCode);

            if ($unit === null) {
                continue;
            }

            foreach ($rows as $row) {
                /** @var Position $position */
                $position = Position::query()->firstOrCreate(
                    ['org_unit_id' => $unit->getKey(), 'code' => $row['code']],
                    [
                        'title' => $row['title'],
                        'grade' => $row['grade'],
                        'managerial_level' => $row['level'],
                        'headcount' => $row['headcount'],
                        'status' => PositionStatus::Active,
                        'valid_from' => '2016-01-01',
                    ],
                );

                $positions[$unitCode.'/'.$row['code']] = $position;
            }
        }

        return $positions;
    }

    /**
     * @return array<string, Certification>
     */
    private function seedCertifications(): array
    {
        $result = [];

        foreach (self::CERTIFICATIONS as $code => $row) {
            $attributes = [
                'name' => $row['name'],
                'issuer' => $row['issuer'],
                'validity_months' => $row['months'],
                'is_field_mandatory' => $row['field'],
                'status' => ActiveStatus::Active,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('certifications', 'valid_until')) {
                $attributes['valid_until'] = Carbon::create(2028, 12, 31)->toDateString();
            }

            /** @var Certification $certification */
            $certification = Certification::query()->firstOrCreate(['code' => $code], $attributes);
            $result[$code] = $certification;
        }

        return $result;
    }

    /**
     * @param  array{unit: string, position: string, name: string, email: string, hired: string, competencies: array<string, string>, certifications: array<string, array{0: string, 1: string}>}  $row
     */
    private function seedPersonnel(int $index, array $row, OrgUnit $unit, Position $position): Personnel
    {
        $normalized = Personnel::normalizeEmail($row['email']);
        $slug = Str::of($row['email'])->before('@')->replace('.', '-')->toString();

        /** @var Personnel|null $personnel */
        $personnel = Personnel::query()->where('normalized_email', $normalized)->first();

        $attributes = [
            'full_name' => $row['name'],
            'email' => $row['email'],
            'phone' => $this->phone($index),
            'national_id' => $this->nationalId($row['email']),
            'job_title' => $position->title,
            'org_unit_id' => $unit->getKey(),
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'status' => PersonnelStatus::Active,
            'personnel_no' => sprintf('KP-%04d', 101 + $index),
            'hired_on' => $row['hired'],
        ];

        $photo = $this->photo($slug, $row['name'], $index);

        if ($photo !== null) {
            $attributes['photo_path'] = $photo;
        }

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
            if (blank($personnel->getAttribute($key))) {
                $personnel->setAttribute($key, $value);
            }
        }

        $personnel->save();

        return $personnel;
    }

    private function seedAssignment(Personnel $personnel, OrgUnit $unit, Position $position, string $hiredOn): void
    {
        PositionAssignment::query()->firstOrCreate(
            ['personnel_id' => $personnel->getKey(), 'position_id' => $position->getKey(), 'valid_until' => null],
            ['is_primary' => true, 'allocation_pct' => 100, 'valid_from' => $hiredOn],
        );

        PersonnelAssignment::query()->firstOrCreate(
            ['personnel_id' => $personnel->getKey(), 'org_unit_id' => $unit->getKey(), 'effective_to' => null],
            ['job_title' => $position->title, 'effective_from' => $hiredOn, 'note' => 'Örnek veri (seed).'],
        );
    }

    /**
     * @param  array<string, string>  $levels
     * @param  \Illuminate\Support\Collection<string, Competency>  $catalog
     */
    private function seedCompetencies(Personnel $personnel, array $levels, $catalog): void
    {
        foreach ($levels as $code => $level) {
            $competency = $catalog->get($code);

            if ($competency === null) {
                continue;
            }

            PersonnelCompetency::query()->firstOrCreate(
                ['personnel_id' => $personnel->getKey(), 'competency_id' => $competency->getKey()],
                ['level' => CompetencyLevel::from($level), 'note' => 'Örnek veri (seed).'],
            );
        }
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $rows
     * @param  array<string, Certification>  $catalog
     */
    private function seedCertificationsFor(Personnel $personnel, array $rows, array $catalog, ?Personnel $verifier): void
    {
        foreach ($rows as $code => [$certificateNo, $issuedOn]) {
            $certification = $catalog[$code] ?? null;

            if ($certification === null) {
                continue;
            }

            $issued = Carbon::parse($issuedOn);
            $validUntil = $issued->copy()->addMonths((int) ($certification->validity_months ?? 36));
            $status = $validUntil->isPast()
                ? CertificationStatus::Expired
                : ($validUntil->lte(now()->addDays(90)) ? CertificationStatus::Expiring : CertificationStatus::Valid);

            PersonnelCertification::query()->firstOrCreate(
                ['personnel_id' => $personnel->getKey(), 'certification_id' => $certification->getKey()],
                [
                    'certificate_no' => $certificateNo,
                    'issued_on' => $issued->toDateString(),
                    'valid_until' => $validUntil->toDateString(),
                    'verified_by_personnel_id' => $verifier?->getKey(),
                    'status' => $status,
                ],
            );
        }
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

    /**
     * @param  array<string, Personnel>  $people
     */
    private function seedTrainings(array $people): void
    {
        $byHandle = [];

        foreach ($people as $email => $personnel) {
            $byHandle[Str::before($email, '@')] = $personnel;
        }

        foreach (self::TRAININGS as $code => $row) {
            /** @var Training $training */
            $training = Training::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $row['name'],
                    'training_kind' => TrainingKind::from($row['kind']),
                    'provider' => $row['provider'],
                    'planned_on' => $row['planned'],
                    'duration_hours' => $row['hours'],
                    'status' => TrainingStatus::from($row['status']),
                ],
            );

            foreach ($row['attendees'] as $offset => $handle) {
                $personnel = $byHandle[$handle] ?? null;

                if ($personnel === null) {
                    continue;
                }

                $completed = $row['status'] === 'completed';

                TrainingAttendance::query()->firstOrCreate(
                    ['training_id' => $training->getKey(), 'personnel_id' => $personnel->getKey()],
                    [
                        'attended_on' => $completed ? $row['planned'] : null,
                        'outcome' => $completed ? TrainingAttendanceOutcome::Passed : TrainingAttendanceOutcome::Registered,
                        'score' => $completed ? 78 + (($offset * 7) % 20) : null,
                    ],
                );
            }
        }
    }

    private function executive(): ?Personnel
    {
        $email = Personnel::normalizeEmail((string) config('konelsis.bootstrap_admin.email'));

        if ($email === null) {
            return null;
        }

        return Personnel::query()->where('normalized_email', $email)->first();
    }

    private function phone(int $index): string
    {
        $suffix = 4100000 + ($index * 37129) % 899999;

        return sprintf('+90 532 %03d %02d %02d', intdiv($suffix, 10000), intdiv($suffix % 10000, 100), $suffix % 100);
    }

    /** Sahte ama algoritmasi gecerli T.C. kimlik numarasi (deterministik). */
    private function nationalId(string $seed): string
    {
        $digits = preg_replace('/\D/', '', md5($seed)) ?? '';
        $digits = str_pad($digits, 9, '7');
        $first = max(1, (int) $digits[0]);
        $base = $first.substr($digits, 1, 8);
        $odd = 0;
        $even = 0;

        for ($i = 0; $i < 9; $i++) {
            if ($i % 2 === 0) {
                $odd += (int) $base[$i];
            } else {
                $even += (int) $base[$i];
            }
        }

        $tenth = (($odd * 7) - $even) % 10;
        $tenth = ($tenth + 10) % 10;
        $sum = $odd + $even + $tenth;
        $eleventh = $sum % 10;

        return $base.$tenth.$eleventh;
    }

    /** Bas harfli avatar (GD); GD yoksa null. */
    private function photo(string $slug, string $name, int $index): ?string
    {
        $path = self::PHOTO_DIRECTORY.'/seed-'.$slug.'.png';
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $path;
        }

        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $palette = [[185, 28, 28], [30, 64, 175], [4, 120, 87], [180, 83, 9], [109, 40, 217], [3, 105, 161], [190, 24, 93], [55, 65, 81]];
        [$r, $g, $b] = $palette[$index % count($palette)];

        $size = 256;
        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));

        $initials = implode('', array_map(
            fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice(preg_split('/\s+/u', trim($name)) ?: [], 0, 2),
        ));

        $white = imagecolorallocate($image, 255, 255, 255);
        $font = 5;
        $scale = 6;
        $width = imagefontwidth($font) * mb_strlen($initials);
        $height = imagefontheight($font);
        $text = imagecreatetruecolor($width, $height);
        imagefill($text, 0, 0, imagecolorallocate($text, $r, $g, $b));
        $textWhite = imagecolorallocate($text, 255, 255, 255);
        $x = 0;

        foreach (mb_str_split($initials) as $letter) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $letter) ?: $letter;
            imagestring($text, $font, $x, 0, $ascii, $textWhite);
            $x += imagefontwidth($font);
        }

        $scaled = imagecreatetruecolor($width * $scale, $height * $scale);
        imagecopyresized($scaled, $text, 0, 0, 0, 0, $width * $scale, $height * $scale, $width, $height);
        imagecopy($image, $scaled, (int) (($size - $width * $scale) / 2), (int) (($size - $height * $scale) / 2), 0, 0, $width * $scale, $height * $scale);
        imagedestroy($text);
        imagedestroy($scaled);

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $disk->put($path, $bytes);
        unset($white);

        return $path;
    }
}
