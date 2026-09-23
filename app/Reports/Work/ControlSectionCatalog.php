<?php

declare(strict_types=1);

namespace App\Reports\Work;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Haftalik kontrol matrisi bolumleri (B36, D-115): kurumun haftalik kontrol
 * cizelgesindeki on alti bolum ve kriterleri kodda tanimlidir. Bolumun
 * uyeleri, `units` listesindeki departmanlarin aktif personelidir (ornegin
 * IK uzmani IK, ISG ve Kalite Kontrol bolumlerinde gorunur). Departmani
 * sistemde bulunmayan bolum (Temizlik, Guvenlik...) o departman acilinca
 * kendiliginden gorunur.
 *
 * `weekly_report` kriteri elle isaretlenmez: kisinin o haftayi kapatip
 * kapatmadigi (haftalik calisma raporu) sistemden gelir.
 */
final class ControlSectionCatalog
{
    public const WEEKLY_REPORT = 'weekly_report';

    private const CORE = ['attendance', 'grooming', 'desk_order', 'hard_records', 'soft_records'];

    /**
     * Bolum kodu => { units, criteria } (sira cizelgedeki siradir).
     *
     * @var array<string, array{units: list<string>, criteria: list<string>}>
     */
    private const SECTIONS = [
        'human_resources' => [
            'units' => ['INSAN_KAYNAKLARI'],
            'criteria' => ['staff_criteria', 'job_posts', 'online_interviews', 'onsite_interviews', 'hard_records', 'soft_records', 'personnel_files', 'associations', 'personnel_leaves', self::WEEKLY_REPORT],
        ],
        'isg' => [
            'units' => ['ISG', 'INSAN_KAYNAKLARI'],
            'criteria' => ['file_order', 'employee_isg', 'intern_isg', 'weekly_isg_meeting', self::WEEKLY_REPORT],
        ],
        'quality_control' => [
            'units' => ['KALITE_KONTROL', 'INSAN_KAYNAKLARI'],
            'criteria' => ['attendance', 'grooming', 'desk_order', 'company_order', 'floor_checks', 'hard_records', 'soft_records', 'expense_checks', 'report_checks'],
        ],
        'business_development' => [
            'units' => ['IS_GELISTIRME'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT],
        ],
        'proposal' => [
            'units' => ['TEKLIF'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT],
        ],
        'project' => [
            'units' => ['PROJE'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT],
        ],
        'information_technology' => [
            'units' => ['BILGI_ISLEM', 'YAZILIM'],
            'criteria' => [...self::CORE, 'production_report', self::WEEKLY_REPORT, 'server_backup', 'it_assets'],
        ],
        'procurement' => [
            'units' => ['SATIN_ALMA'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT, 'inventory_lists'],
        ],
        'accounting' => [
            'units' => ['MUHASEBE'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT],
        ],
        'construction' => [
            'units' => ['INSAAT'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT],
        ],
        'workshop' => [
            'units' => ['ATOLYE'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT, 'workshop_order', 'inventory_lists'],
        ],
        'field_service' => [
            'units' => ['SAHA'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT, 'production_report'],
        ],
        'administrative_manager' => [
            'units' => ['YONETIM', 'IDARI'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT, 'customer_meetings', 'personnel_control'],
        ],
        'electrical' => [
            'units' => ['ELEKTRIK'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT],
        ],
        'logistics' => [
            'units' => ['LOJISTIK'],
            'criteria' => [...self::CORE, self::WEEKLY_REPORT],
        ],
        'cleaning' => [
            'units' => ['TEMIZLIK'],
            'criteria' => ['attendance', 'grooming', 'kitchen_order', 'general_cleaning'],
        ],
        'executive_driver' => [
            'units' => ['MAKAM_SOFORU', 'SOFOR'],
            'criteria' => ['attendance', 'grooming', 'vehicle_list', 'executive_vehicles'],
        ],
        'security' => [
            'units' => ['GUVENLIK'],
            'criteria' => ['attendance', 'grooming', 'entrance_order', 'visitor_log', 'vehicle_handover_log'],
        ],
    ];

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys(self::SECTIONS);
    }

    public function has(string $section): bool
    {
        return array_key_exists($section, self::SECTIONS);
    }

    /**
     * @return list<string>
     */
    public function units(string $section): array
    {
        return self::SECTIONS[$section]['units'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function criteria(string $section): array
    {
        return self::SECTIONS[$section]['criteria'] ?? [];
    }

    /**
     * Elle isaretlenen kriterler (haftalik rapor haric).
     *
     * @return list<string>
     */
    public function manualCriteria(string $section): array
    {
        return array_values(array_filter($this->criteria($section), static fn (string $code): bool => $code !== self::WEEKLY_REPORT));
    }

    public function hasWeeklyReport(string $section): bool
    {
        return in_array(self::WEEKLY_REPORT, $this->criteria($section), true);
    }

    public function sectionLabel(string $section): string
    {
        $key = 'work_item.control.sections.'.$section;

        return Lang::has($key) ? (string) __($key) : Str::headline($section);
    }

    public function criterionLabel(string $criterion): string
    {
        $key = 'work_item.control.criteria.'.$criterion;

        return Lang::has($key) ? (string) __($key) : Str::headline($criterion);
    }

    /**
     * Departman kodu hangi bolumlerde?
     *
     * @return list<string>
     */
    public function sectionsForUnit(?string $unitCode): array
    {
        $unit = strtoupper(trim((string) $unitCode));

        return array_values(array_filter(
            $this->codes(),
            fn (string $section): bool => in_array($unit, $this->units($section), true),
        ));
    }
}
