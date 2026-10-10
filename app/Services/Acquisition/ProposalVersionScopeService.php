<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Acquisition\ProposalScopeDocumentRole;
use App\Exceptions\RecordNotFoundException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\ProposalVersionScope;
use App\Models\Acquisition\ProposalVersionScopeDocument;
use App\Query\Document\FixedDocumentQueries;
use App\Services\AbstractService;
use App\Services\Acquisition\Concerns\GuardsVersionChildren;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Acquisition\CostLists;
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
 * Kapsam listesi (Excel) Dokumanlar'da `KPS` turunde belgedir; her surum
 * baktigi revizyonu (scope_document_revision_id) saklar, eski surum eski
 * dosyayi gostermeye devam eder.
 *
 * Maliyet listesi (B51, D-181): kapsam listesinin yaninda, tip basina bir ya
 * da birden fazla `MLY` belgesi (proposal_version_scope_documents). Yeni surum
 * onceki surumun satirlarini ayni revizyonla tasir (belge kopyalanmaz). Tasima
 * B51 varken hep yapilir; yukleme yalniz ozellik acikken (CostLists) islenir.
 *
 * D-186 (9 Ekim 2026 kullanici karari: teklifte belge revizyonu yok, surumleme
 * personelde): yuklenen her dosya yeni belgedir ("ayni ad = yeni revizyon"
 * kurali teklifte kalkti). Formdaki `removed_scope_file` / `removed_cost_files`
 * (cipin "x"i ile isaretlenen revizyon kimlikleri) o belgeyi surumden ayirir;
 * Document kaydi Dokumanlar'da kalir.
 * Duzenle ayni surumu yerinde, "Yeni teklif surumu" onceki surumden tasiyarak yazar.
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

    /** D-181: formda tipin maliyet listesi dosyalari (coklu) ve ozgun adlari. */
    public const COST_FILES_KEY = 'cost_files';

    public const COST_FILES_NAME_KEY = 'cost_files_name';

    /** D-186: formda kaldirilmak uzere isaretlenen kapsam listesi revizyonu (dizi). */
    public const REMOVED_SCOPE_FILE_KEY = 'removed_scope_file';

    /** D-186: formda kaldirilmak uzere isaretlenen maliyet listesi revizyonlari (dizi). */
    public const REMOVED_COST_FILES_KEY = 'removed_cost_files';

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
        // D-177 (B50): Otomasyon / Process yalniz toplam maliyet ve toplam satis tasir.
        'automation' => ['total_cost', 'total_sales'],
    ];

    /** @var list<string> Her tipte bulunan ortak alanlar. */
    private const COMMON_FIELDS = ['note'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly DocumentService $documents,
        private readonly FixedDocumentQueries $fixedDocuments,
        private readonly ProposalVersionScopeDocumentService $scopeDocuments,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Surumun kapsamlarini formdaki tiplerle yazar. $previous verilirse (yeni
     * surum) dosya yuklenmeyen tipin kapsam listesi onceki surumden tasinir
     * (D-186: carpi ile cikarilmadiysa); yuklenen dosya yeni belgedir.
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
                    /** @var ProposalVersionScope $scope */
                    $scope = $this->create([
                        ...$attributes,
                        'proposal_version_id' => $version->getKey(),
                        'scope_type' => $type,
                    ]);
                } else {
                    /** @var ProposalVersionScope $scope */
                    $scope = $this->update($current, $attributes);
                }

                // D-181 (B51): kapsamin maliyet listeleri.
                $this->syncCostDocuments($case, $type, $scope, $current === null ? $source : null, $input);
            }

            foreach ($existing as $type => $scope) {
                if (! in_array($type, $selected, true)) {
                    $this->removeScopeDocuments($scope);
                    $this->delete($scope);
                }
            }
        });
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
     * Kapsam listesi (D-186): yeni dosya yeni KPS belgesidir ve kapsamin
     * listesi olur; dosya yoksa kaynagin belgesi ve revizyonu tasinir, cipin
     * "x"i ile isaretlendiyse (removed_scope_file) kapsamin listesi bosalir.
     * Eski belge Dokumanlar'da kalir.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: int|null, 1: int|null}
     */
    private function storeScopeFile(BusinessCase $case, string $type, ?ProposalVersionScope $source, array $input): array
    {
        $tempPath = self::firstString($input['scope_file'] ?? null);

        if ($tempPath === null) {
            $revisionId = $source?->scope_document_revision_id === null ? null : (int) $source->scope_document_revision_id;
            if ($revisionId !== null && in_array($revisionId, self::removedIds($input, self::REMOVED_SCOPE_FILE_KEY), true)) {
                return [null, null];
            }

            return [
                $source?->scope_document_id === null ? null : (int) $source->scope_document_id,
                $revisionId,
            ];
        }

        $originalName = self::originalName($input['scope_file_name'] ?? null, $tempPath);

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

    /**
     * Kapsamin maliyet listeleri (B51, D-181; D-186).
     *
     * - $carryFrom verilirse (yeni surum): onceki surumun kapsam satirindaki
     *   tutulan maliyet listeleri ayni revizyonla bu kapsama baglanir.
     * - $carryFrom yoksa satirlar zaten bu kapsamdadir (Duzenle, yerinde):
     *   carpi ile cikarilan satir kapsamdan ayrilir (belge Dokumanlar'da kalir).
     * - Yuklenen her dosya yeni MLY belgesidir (teklifte revizyon yok, D-186).
     *
     * @param  array<string, mixed>  $input
     */
    private function syncCostDocuments(BusinessCase $case, string $type, ProposalVersionScope $scope, ?ProposalVersionScope $carryFrom, array $input): void
    {
        if (! SchemaReadiness::hasBatch('B51')) {
            return;
        }

        $uploads = CostLists::enabled() ? self::costUploads($input) : [];
        $removed = self::removedIds($input, self::REMOVED_COST_FILES_KEY);
        $inPlace = $carryFrom === null;

        if ($inPlace && $uploads === [] && $removed === []) {
            return;
        }

        // Tur dosya tasinmadan once dogrulanir (tasima geri alinamaz).
        $typeId = $uploads === []
            ? null
            : ($this->fixedDocuments->documentTypeId(ProposalScopeDocumentRole::CostList->documentTypeCode()) ?? throw RecordNotFoundException::make());

        /** @var list<ProposalVersionScopeDocument> $rows */
        $rows = ($carryFrom ?? $scope)->costDocuments()->get()->all();
        $sortOrder = $inPlace && $rows !== []
            ? max(array_map(static fn (ProposalVersionScopeDocument $row): int => (int) $row->sort_order, $rows)) + 1
            : 0;

        foreach ($rows as $row) {
            $kept = ! in_array((int) $row->document_revision_id, $removed, true);

            if ($inPlace && ! $kept) {
                $this->scopeDocuments->delete($row);
            } elseif (! $inPlace && $kept) {
                $this->linkCostRevision($scope, (int) $row->document_revision_id, $sortOrder);
            }
        }

        foreach ($uploads as $upload) {
            $this->linkCostRevision($scope, $this->newCostDocument($case, $type, (int) $typeId, $upload), $sortOrder);
        }
    }

    /**
     * Formda kaldirilmak uzere isaretlenen revizyon kimlikleri (D-186, cipin "x"i).
     *
     * @param  array<string, mixed>  $input
     * @return list<int>
     */
    private static function removedIds(array $input, string $key): array
    {
        $ids = [];

        foreach ((array) ($input[$key] ?? []) as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        return $ids;
    }

    /** Kaldirilan kapsamin belge satirlari (kapsam satiri RESTRICT ile korunur). */
    private function removeScopeDocuments(ProposalVersionScope $scope): void
    {
        if (! SchemaReadiness::hasBatch('B51')) {
            return;
        }

        foreach (ProposalVersionScopeDocument::query()->where('proposal_version_scope_id', $scope->getKey())->get() as $row) {
            $this->scopeDocuments->delete($row);
        }
    }

    /**
     * Formdaki maliyet listesi dosyalari: [gecici yol, ozgun ad].
     *
     * @param  array<string, mixed>  $input
     * @return list<array{path: string, name: string|null}>
     */
    private static function costUploads(array $input): array
    {
        $uploads = [];

        foreach (self::strings($input[self::COST_FILES_KEY] ?? null) as $path) {
            $uploads[] = ['path' => $path, 'name' => self::originalName($input[self::COST_FILES_NAME_KEY] ?? null, $path)];
        }

        return $uploads;
    }

    /**
     * Yeni MLY belgesi + ilk revizyon; revizyon kimligini doner.
     *
     * @param  array{path: string, name: string|null}  $upload
     */
    private function newCostDocument(BusinessCase $case, string $type, int $typeId, array $upload): int
    {
        $suffix = ProjectScopeType::from($type)->getLabel().' maliyet listesi';

        $document = $this->documents->createWithInitialRevision([
            'document_type_id' => $typeId,
            'title' => Str::limit((string) $case->title, 255 - mb_strlen(' – '.$suffix), '').' – '.$suffix,
            'owner_personnel_id' => $this->actor->personnelId() ?? $case->owner_employee_id,
            'default_language' => 'tr',
            'file_temp_path' => $upload['path'],
            'file_original_name' => $upload['name'],
        ]);

        return (int) ($document->refresh()->displayRevision()?->getKey() ?? throw RecordNotFoundException::make());
    }

    private function linkCostRevision(ProposalVersionScope $scope, int $revisionId, int &$sortOrder): void
    {
        $this->scopeDocuments->create([
            'proposal_version_scope_id' => $scope->getKey(),
            'document_revision_id' => $revisionId,
            'document_role' => ProposalScopeDocumentRole::CostList->value,
            'sort_order' => $sortOrder++,
        ]);
    }

    /**
     * Doldurulmus butun yollar (FileUpload tek dosyada metin, coklu dosyada dizi).
     *
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        $paths = [];

        foreach (is_array($value) ? $value : [$value] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $paths[] = trim($candidate);
            }
        }

        return array_values(array_unique($paths));
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
