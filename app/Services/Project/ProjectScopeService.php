<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCaseScope;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\ProposalVersionScope;
use App\Models\Project\Project;
use App\Models\Project\ProjectScope;
use App\Services\AbstractService;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Proje tipleri ve projenin kendi kapsam satirlari (B48, D-174; 8 Ekim 2026
 * kullanici talimati: "Yapi Is gelistirmeden gelen teklife gecen ve tekliften
 * projeye donustugunde eslesecek ... projenin isterleri farklidir, birbirine
 * isterler karismadan ortak mantikta benzerlik tasidigi sekilde").
 *
 * Ortak mantik: ayni tip katalogu (ProjectScopeType), ayni coklu secim ve tip
 * basina kendi bolumu (BusinessCaseScopeService / ProposalVersionScopeService
 * gibi sync()). Ayri isterler: teklif birim fiyat / maliyet / satis tasir,
 * proje ise olculerini (TYPE_FIELDS), sozlesme tutarini ve butcesini.
 *
 * copyFromAcquisition(): proje acilirken (devir kabulu / tekliften donusum)
 * kabul edilen teklif surumunun kapsami projeye kopyalanir: miktarlar aynen,
 * toplam satis -> sozlesme tutari, toplam maliyet -> butce, not; kaynak satir
 * source_proposal_version_scope_id ile izlenir. Surumde kapsam yoksa potansiyel
 * isin secili tipleri (business_case_scopes) alinir (B43 oncesi GES MW ve
 * tutarlariyla). RES teklif kalemleri (malzeme / insaat / montaj) maliyet-satis
 * ayrimi tasimadigi icin tutara cevrilmez.
 */
final class ProjectScopeService extends AbstractService
{
    protected string $model = ProjectScope::class;

    protected string $orderBy = 'scope_type';

    /**
     * Tip => projenin o tipte tuttugu alanlar (sira ekrandaki sira).
     *
     * @var array<string, list<string>>
     */
    public const TYPE_FIELDS = [
        'ges' => ['capacity_mwp', 'contract_amount', 'budget_amount'],
        'res' => ['capacity_mw', 'unit_count', 'contract_amount', 'budget_amount'],
        'tm' => ['unit_count', 'contract_amount', 'budget_amount'],
        'hes' => ['capacity_mw', 'unit_count', 'contract_amount', 'budget_amount'],
        'bes' => ['power_mwe', 'energy_mwh', 'contract_amount', 'budget_amount'],
        'enh_eih' => ['length_km', 'contract_amount', 'budget_amount'],
        // D-177 (B50): Otomasyon / Process'in olcusu yok; sozlesme tutari ve butce.
        'automation' => ['contract_amount', 'budget_amount'],
    ];

    /** @var list<string> Her tipte bulunan ortak alanlar. */
    public const COMMON_FIELDS = ['note'];

    /** Tip => projeye ait bilesen (ComponentDefinition) kodu; proje tipi kodu bundan turer. */
    public const COMPONENT_CODES = [
        'ges' => 'GES',
        'res' => 'RES',
        'hes' => 'HES',
        'bes' => 'BESS',
        'enh_eih' => 'ENH',
        'tm' => 'SUBSTATION',
        // D-177: katalogdaki "Otomasyon" bileseni (ProjectCatalogSeeder).
        'automation' => 'AUTOMATION',
    ];

    /**
     * Secilen tipleri ve tip basina alanlari projeyle eslestirir: secilen tip
     * yoksa acilir, varsa guncellenir, secimi kaldirilan tip silinir (hareket
     * kaydiyla). Tipe ait olmayan alanlar yok sayilir; bos metin NULL olur.
     *
     * @param  list<mixed>  $types
     * @param  array<string, mixed>  $rows  tip => alanlar
     */
    public function sync(Project $project, array $types, array $rows): void
    {
        if (! SchemaReadiness::hasBatch('B48')) {
            return;
        }

        $selected = self::normaliseTypes($types);

        $this->transactions->run(function () use ($project, $selected, $rows): void {
            $existing = $this->keyed($project);

            foreach ($selected as $type) {
                $input = is_array($rows[$type] ?? null) ? $rows[$type] : [];
                $attributes = self::attributesFor($type, $input);
                $current = $existing[$type] ?? null;

                if ($current === null) {
                    $this->create([...$attributes, 'project_id' => $project->getKey(), 'scope_type' => $type]);

                    continue;
                }

                if ($attributes !== []) {
                    $this->update($current, $attributes);
                }
            }

            foreach ($existing as $type => $scope) {
                if (! in_array($type, $selected, true)) {
                    $this->delete($scope);
                }
            }
        });
    }

    /**
     * Proje acilirken kapsami kopyalar (bkz. sinif aciklamasi). Projede zaten
     * satir varsa hicbir sey yapilmaz.
     */
    public function copyFromAcquisition(Project $project, BusinessCase $case, ?ProposalVersion $version): void
    {
        if (! SchemaReadiness::hasBatch('B48')) {
            return;
        }

        $this->transactions->run(function () use ($project, $case, $version): void {
            if (ProjectScope::query()->where('project_id', $project->getKey())->exists()) {
                return;
            }

            $rows = $version !== null && SchemaReadiness::hasBatch('B43') ? $this->fromProposalVersion($version) : [];

            if ($rows === [] && SchemaReadiness::hasBatch('B29')) {
                $rows = $this->fromBusinessCase($case);
            }

            foreach ($rows as $type => $attributes) {
                $this->create([...$attributes, 'project_id' => $project->getKey(), 'scope_type' => $type]);
            }
        });
    }

    /**
     * Duzenleme formu icin projenin tipleri ve alanlari.
     *
     * @return array{scope_types: list<string>, scopes: array<string, array<string, string|null>>}
     */
    public function formData(Project $project): array
    {
        if (! SchemaReadiness::hasBatch('B48')) {
            return ['scope_types' => [], 'scopes' => []];
        }

        $types = [];
        $scopes = [];

        foreach ($this->keyed($project) as $type => $scope) {
            $types[] = $type;
            $row = [];

            foreach ([...self::TYPE_FIELDS[$type] ?? [], ...self::COMMON_FIELDS] as $field) {
                $raw = $scope->getAttribute($field);
                $row[$field] = $raw === null ? null : (string) $raw;
            }

            $scopes[$type] = $row;
        }

        return ['scope_types' => $types, 'scopes' => $scopes];
    }

    /**
     * Ilk secili tipin bilesen kodu (createDirect'te proje tipi kodu bossa).
     *
     * @param  list<mixed>  $types
     */
    public static function componentCodeFor(array $types): ?string
    {
        foreach (self::normaliseTypes($types) as $type) {
            return self::COMPONENT_CODES[$type] ?? null;
        }

        return null;
    }

    /**
     * Gecerli, tekil tip degerleri; bilinmeyenler yok sayilir.
     *
     * @param  list<mixed>  $types
     * @return list<string>
     */
    public static function normaliseTypes(array $types): array
    {
        $values = [];

        foreach ($types as $type) {
            $value = $type instanceof BackedEnum ? (string) $type->value : trim((string) $type);

            if ($value === '' || ProjectScopeType::tryFrom($value) === null || in_array($value, $values, true)) {
                continue;
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $type = $record->getAttribute('scope_type');
        $value = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        return [
            'kapsam' => ProjectScopeType::tryFrom($value)?->getLabel() ?? $value,
            'project_id' => $record->getAttribute('project_id'),
        ];
    }

    /**
     * @return array<string, ProjectScope>
     */
    private function keyed(Project $project): array
    {
        return ProjectScope::query()
            ->where('project_id', $project->getKey())
            ->get()
            ->keyBy(static fn (ProjectScope $scope): string => self::typeValue($scope->getAttribute('scope_type')))
            ->all();
    }

    /**
     * Teklif surumunun kapsami -> proje satirlari.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fromProposalVersion(ProposalVersion $version): array
    {
        $rows = [];

        foreach (ProposalVersionScope::query()->where('proposal_version_id', $version->getKey())->orderBy('id')->get() as $scope) {
            $type = self::typeValue($scope->getAttribute('scope_type'));

            if (ProjectScopeType::tryFrom($type) === null) {
                continue;
            }

            $rows[$type] = self::onlyTypeFields($type, [
                'capacity_mwp' => $scope->getAttribute('capacity_mwp'),
                'power_mwe' => $scope->getAttribute('power_mwe'),
                'energy_mwh' => $scope->getAttribute('energy_mwh'),
                'length_km' => $scope->getAttribute('length_km'),
                'contract_amount' => $scope->getAttribute('total_sales'),
                'budget_amount' => $scope->getAttribute('total_cost'),
                'note' => $scope->getAttribute('note'),
            ]) + ['source_proposal_version_scope_id' => (int) $scope->getKey()];
        }

        return $rows;
    }

    /**
     * Potansiyel isin secili tipleri -> proje satirlari (B29 tutarlariyla).
     *
     * @return array<string, array<string, mixed>>
     */
    private function fromBusinessCase(BusinessCase $case): array
    {
        $rows = [];

        foreach (BusinessCaseScope::query()->where('business_case_id', $case->getKey())->orderBy('id')->get() as $scope) {
            $type = self::typeValue($scope->getAttribute('scope_type'));

            if (ProjectScopeType::tryFrom($type) === null) {
                continue;
            }

            $rows[$type] = self::onlyTypeFields($type, match ($type) {
                'ges' => ['capacity_mwp' => $scope->getAttribute('capacity_mw'), 'contract_amount' => $scope->getAttribute('sales_amount'), 'budget_amount' => $scope->getAttribute('cost_amount')],
                'hes' => ['contract_amount' => $scope->getAttribute('sales_amount'), 'budget_amount' => $scope->getAttribute('cost_amount')],
                'tm' => ['contract_amount' => $scope->getAttribute('tm_total_sales'), 'budget_amount' => $scope->getAttribute('tm_total_cost')],
                default => [],
            } + ['note' => $scope->getAttribute('note')]);
        }

        return $rows;
    }

    /**
     * Yalniz tipe ait ve dolu alanlar.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function onlyTypeFields(string $type, array $values): array
    {
        $allowed = [...self::TYPE_FIELDS[$type] ?? [], ...self::COMMON_FIELDS];
        $out = [];

        foreach ($values as $field => $value) {
            if (in_array($field, $allowed, true) && $value !== null && $value !== '') {
                $out[$field] = $value;
            }
        }

        return $out;
    }

    /**
     * Tipe ait ve formda gelen alanlar; bos metin NULL olur.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function attributesFor(string $type, array $input): array
    {
        $attributes = [];

        foreach ([...self::TYPE_FIELDS[$type] ?? [], ...self::COMMON_FIELDS] as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];

            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }

            $attributes[$field] = $value;
        }

        return $attributes;
    }

    private static function typeValue(mixed $type): string
    {
        return $type instanceof BackedEnum ? (string) $type->value : (string) $type;
    }
}
