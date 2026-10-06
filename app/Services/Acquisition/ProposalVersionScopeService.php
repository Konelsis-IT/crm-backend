<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\ProjectScopeType;
use App\Exceptions\RecordNotFoundException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\ProposalVersionScope;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Query\Document\FixedDocumentQueries;
use App\Services\AbstractService;
use App\Services\Acquisition\Concerns\GuardsVersionChildren;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentRevisionService;
use App\Services\Document\DocumentService;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Teklif surumunun proje kapsamlari (B43, D-155): kapsam potansiyel isten
 * teklife tasindi; proje tipi potansiyel iste secilir, tutarlar teklif
 * surumunde tutulur.
 *
 * Tip basina alanlar (TYPE_FIELDS, 5 Ekim 2026 kullanici izgarasi):
 * - GES: MWp; GES Maliyet/MWp + Toplam maliyet; GES Satis/MWp + Toplam satis.
 * - BESS: MWe, MWh; Maliyet/MWh + Toplam maliyet; Satis/MWh + Toplam satis.
 * - ENH/EIH: Km; Maliyet/Km + Toplam maliyet; Satis/Km + Toplam satis.
 * - TM: Toplam maliyet, Toplam satis; Maliyet/Fider.
 * - HES: Maliyet (toplam), Satis (toplam); Maliyet/Jenerator-Turbin.
 * - RES: Respark malzeme / insaat / montaj (degismedi).
 *
 * Kapsam listesi (Excel) Dokumanlar'da `KPS` turunde belgedir: satir onceki
 * surumden belge tasiyorsa yeni yukleme ayni belgenin yeni revizyonudur; her
 * surum baktigi revizyonu (scope_document_revision_id) saklar, eski surum eski
 * dosyayi gostermeye devam eder.
 *
 * Marj (margin()): toplam maliyeti ve toplam satisi olan tiplerin
 * (Σ satis - Σ maliyet) / Σ satis orani. RES kalemleri maliyet / satis
 * ayrimi tasimadigi icin marja girmez.
 */
final class ProposalVersionScopeService extends AbstractService
{
    use GuardsVersionChildren;

    protected string $model = ProposalVersionScope::class;

    protected string $orderBy = 'scope_type';

    /** Kapsam listesi belgesinin dokuman turu kodu. */
    private const SCOPE_DOCUMENT_TYPE_CODE = 'KPS';

    /**
     * @var array<string, list<string>>
     */
    public const TYPE_FIELDS = [
        'ges' => ['capacity_mwp', 'unit_cost', 'total_cost', 'unit_sales', 'total_sales'],
        'bes' => ['power_mwe', 'energy_mwh', 'unit_cost', 'total_cost', 'unit_sales', 'total_sales'],
        'enh_eih' => ['length_km', 'unit_cost', 'total_cost', 'unit_sales', 'total_sales'],
        'tm' => ['total_cost', 'total_sales', 'unit_cost'],
        'hes' => ['total_cost', 'total_sales', 'unit_cost'],
        'res' => ['res_material_amount', 'res_construction_amount', 'res_assembly_amount'],
    ];

    /** @var list<string> Her tipte bulunan ortak alanlar. */
    private const COMMON_FIELDS = ['note'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly DocumentService $documents,
        private readonly DocumentRevisionService $revisions,
        private readonly FixedDocumentQueries $fixedDocuments,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Surumun kapsamlarini formdaki tiplerle yazar. $previous verilirse (yeni
     * surum) dosya yuklenmeyen tipin kapsam listesi onceki surumden aynen
     * tasinir; yuklenen dosya ayni belgenin yeni revizyonu olur.
     *
     * @param  list<mixed>  $types  potansiyel iste secili proje tipleri
     * @param  array<string, array<string, mixed>>  $rows  tip => alanlar (+ scope_file, scope_file_name)
     */
    public function sync(ProposalVersion $version, BusinessCase $case, array $types, array $rows, ?ProposalVersion $previous = null): void
    {
        $selected = self::normaliseTypes($types);

        $this->transactions->run(function () use ($version, $case, $selected, $rows, $previous): void {
            $this->assertProposalVersionEditable($version->getKey());

            $existing = $this->keyed($version);
            $carried = $previous !== null ? $this->keyed($previous) : [];

            foreach ($selected as $type) {
                $input = is_array($rows[$type] ?? null) ? $rows[$type] : [];
                $current = $existing[$type] ?? null;
                $attributes = self::attributesFor($type, $input);
                $source = $current ?? ($carried[$type] ?? null);

                // Yeni surum: formda gelmeyen alanlar (or. not) onceki surumden tasinir.
                if ($current === null && $source !== null) {
                    $attributes = [...self::storedAttributes($type, $source), ...$attributes];
                }

                [$documentId, $revisionId] = $this->storeScopeFile($case, $type, $source, $input);
                $attributes['scope_document_id'] = $documentId;
                $attributes['scope_document_revision_id'] = $revisionId;

                if ($current === null) {
                    $this->create([
                        ...$attributes,
                        'proposal_version_id' => $version->getKey(),
                        'scope_type' => $type,
                    ]);

                    continue;
                }

                $this->update($current, $attributes);
            }

            foreach ($existing as $type => $scope) {
                if (! in_array($type, $selected, true)) {
                    $this->delete($scope);
                }
            }
        });
    }

    /**
     * Formdaki kapsamlar kayitli surumden farkli mi (yeni surum karari).
     * Yuklenen dosya her zaman degisikliktir.
     *
     * @param  list<mixed>  $types
     * @param  array<string, array<string, mixed>>  $rows
     */
    public function differs(?ProposalVersion $version, array $types, array $rows): bool
    {
        $selected = self::normaliseTypes($types);
        $existing = $version !== null ? $this->keyed($version) : [];

        if (array_diff($selected, array_keys($existing)) !== [] || array_diff(array_keys($existing), $selected) !== []) {
            return true;
        }

        foreach ($selected as $type) {
            $input = is_array($rows[$type] ?? null) ? $rows[$type] : [];

            if (self::firstString($input['scope_file'] ?? null) !== null) {
                return true;
            }

            $scope = $existing[$type];

            foreach (self::attributesFor($type, $input) as $field => $value) {
                if (! self::sameValue($scope->getAttribute($field), $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Marj yuzdesi: toplam maliyeti ve satisi olan tiplerden; hesaplanamazsa null.
     *
     * @param  iterable<array<string, mixed>|ProposalVersionScope>  $scopes
     */
    public static function margin(iterable $scopes): ?float
    {
        $cost = 0.0;
        $sales = 0.0;

        foreach ($scopes as $scope) {
            $totalCost = self::number($scope instanceof Model ? $scope->getAttribute('total_cost') : ($scope['total_cost'] ?? null));
            $totalSales = self::number($scope instanceof Model ? $scope->getAttribute('total_sales') : ($scope['total_sales'] ?? null));

            if ($totalCost === null || $totalSales === null) {
                continue;
            }

            $cost += $totalCost;
            $sales += $totalSales;
        }

        if ($sales <= 0.0) {
            return null;
        }

        return round(($sales - $cost) / $sales * 100, 4);
    }

    /**
     * Toplam satis (kapsamlardan): toplam satisi dolu tiplerin toplami; yoksa null.
     *
     * @param  iterable<array<string, mixed>|ProposalVersionScope>  $scopes
     */
    public static function totalSales(iterable $scopes): ?float
    {
        $sum = null;

        foreach ($scopes as $scope) {
            $value = self::number($scope instanceof Model ? $scope->getAttribute('total_sales') : ($scope['total_sales'] ?? null));

            if ($value !== null) {
                $sum = ($sum ?? 0.0) + $value;
            }
        }

        return $sum;
    }

    /**
     * Formdaki secili tiplerin satirlari (marj / toplam hesaplari icin).
     *
     * @param  list<mixed>  $types
     * @param  array<string, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    public static function selectedRows(array $types, array $rows): array
    {
        $selected = [];

        foreach (self::normaliseTypes($types) as $type) {
            $selected[] = is_array($rows[$type] ?? null) ? $rows[$type] : [];
        }

        return $selected;
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
            'proposal_version_id' => $record->getAttribute('proposal_version_id'),
        ];
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
     * @return array<string, ProposalVersionScope>
     */
    private function keyed(ProposalVersion $version): array
    {
        return ProposalVersionScope::query()
            ->with('scopeDocument')
            ->where('proposal_version_id', $version->getKey())
            ->get()
            ->keyBy(static fn (ProposalVersionScope $scope): string => $scope->scope_type instanceof BackedEnum ? (string) $scope->scope_type->value : (string) $scope->scope_type)
            ->all();
    }

    /**
     * Tipe ait ve formda gelen alanlar; digerleri yok sayilir (formda olmayan
     * alan kayitli degerini korur, "degisti" sayilmaz), bos metin NULL olur.
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

    /**
     * Kayitli satirin tipe ait alanlari (yeni surume tasima icin).
     *
     * @return array<string, mixed>
     */
    private static function storedAttributes(string $type, ProposalVersionScope $scope): array
    {
        $attributes = [];

        foreach ([...self::TYPE_FIELDS[$type] ?? [], ...self::COMMON_FIELDS] as $field) {
            $attributes[$field] = $scope->getAttribute($field);
        }

        return $attributes;
    }

    /**
     * Kapsam listesi: yeni dosya yoksa kaynagin belgesi ve revizyonu aynen;
     * dosya varsa kaynagin belgesine yeni revizyon ya da yeni belge.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: int|null, 1: int|null}
     */
    private function storeScopeFile(BusinessCase $case, string $type, ?ProposalVersionScope $source, array $input): array
    {
        $tempPath = self::firstString($input['scope_file'] ?? null);

        if ($tempPath === null) {
            return [
                $source?->scope_document_id === null ? null : (int) $source->scope_document_id,
                $source?->scope_document_revision_id === null ? null : (int) $source->scope_document_revision_id,
            ];
        }

        $originalName = self::originalName($input['scope_file_name'] ?? null, $tempPath);

        /** @var Document|null $document */
        $document = $source?->scopeDocument;

        if ($document !== null) {
            /** @var DocumentRevision $revision */
            $revision = $this->revisions->create([
                'document_id' => $document->getKey(),
                'title' => $document->title,
                'language' => 'tr',
                'purpose' => 'for_review',
                'file_temp_path' => $tempPath,
                'file_original_name' => $originalName,
            ]);

            return [(int) $document->getKey(), (int) $revision->getKey()];
        }

        $typeId = $this->fixedDocuments->documentTypeId(self::SCOPE_DOCUMENT_TYPE_CODE) ?? throw RecordNotFoundException::make();
        $suffix = ProjectScopeType::from($type)->getLabel().' kapsam listesi';

        $document = $this->documents->createWithInitialRevision([
            'document_type_id' => $typeId,
            // documents.title 255 karakterle sinirlidir; potansiyel is basligi kirpilir.
            'title' => Str::limit((string) $case->title, 255 - mb_strlen(' – '.$suffix), '').' – '.$suffix,
            'owner_personnel_id' => $this->actor->personnelId() ?? $case->owner_employee_id,
            'default_language' => 'tr',
            'file_temp_path' => $tempPath,
            'file_original_name' => $originalName,
        ]);

        return [(int) $document->getKey(), $document->refresh()->displayRevision()?->getKey()];
    }

    private static function sameValue(mixed $stored, mixed $incoming): bool
    {
        $left = self::number($stored);
        $right = self::number($incoming);

        if ($left !== null || $right !== null) {
            return $left !== null && $right !== null && abs($left - $right) < 0.005;
        }

        return trim((string) $stored) === trim((string) $incoming);
    }

    private static function number(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /** Filament FileUpload tek dosyada metin, coklu dosyada dizi verir; ilk dolu yolu alir. */
    private static function firstString(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /** storeFileNamesIn tek dosyada metin, coklu dosyada yol => ad dizisi verir. */
    private static function originalName(mixed $value, string $tempPath): ?string
    {
        if (is_array($value)) {
            $value = $value[$tempPath] ?? reset($value);
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
