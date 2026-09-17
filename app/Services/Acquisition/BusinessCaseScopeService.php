<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Enums\Acquisition\ProjectScopeType;
use App\Exceptions\RecordNotFoundException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCaseScope;
use App\Models\Document\Document;
use App\Query\Document\FixedDocumentQueries;
use App\Services\AbstractService;
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
 * Is dosyasi proje kapsamlari servisi (B29, D-101).
 *
 * sync() bes temel islemin disinda, sihirbazdan gelen "Proje tip secimi"
 * onay kutularini ve tip basina tutar alanlarini tek transaction'da
 * business_case_scopes satirlarina isler: secilen tip yoksa acilir, varsa
 * guncellenir, secimi kaldirilan tip silinir (hareket kaydiyla). Tipe ait
 * olmayan alanlar yok sayilir; bos metin NULL olur.
 *
 * Kapsam listesi (Excel) Dokumanlar'da `KPS` turunde bir belge olarak
 * saklanir: satirin belgesi yoksa yeni belge + ilk revizyon acilir ve
 * scope_document_id'ye yazilir; belgesi varsa ayni belgeye yeni revizyon
 * eklenir (gecmis korunur). Secimi kaldirilan tipin belgesi silinmez.
 */
final class BusinessCaseScopeService extends AbstractService
{
    protected string $model = BusinessCaseScope::class;

    protected string $orderBy = 'scope_type';

    /** Kapsam listesi belgesinin dokuman turu kodu. */
    private const SCOPE_DOCUMENT_TYPE_CODE = 'KPS';

    /** @var list<string> Her tipte bulunan ortak alanlar. */
    private const COMMON_FIELDS = ['note'];

    /**
     * Tip => o tipe ait tutar alanlari. HES, GES'in Maliyet/Satis kolonlarini
     * paylasir (ayni anlam, ayri satir; scope_type karismayi engeller),
     * kendi ucuncu kalemi icin B30'la eklenen hes_unit_cost'u kullanir.
     * Listede olmayan tipler (BES / ENH-EIH) simdilik yalniz secim satiri
     * tasir.
     *
     * @var array<string, list<string>>
     */
    private const TYPE_FIELDS = [
        'ges' => ['capacity_mw', 'cost_amount', 'sales_amount', 'cost_per_mw', 'sales_per_mw'],
        'res' => ['res_material_amount', 'res_construction_amount', 'res_assembly_amount'],
        'tm' => ['tm_total_cost', 'tm_total_sales', 'tm_feeder_cost'],
        'hes' => ['cost_amount', 'sales_amount', 'hes_unit_cost'],
    ];

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
     * Secilen tipleri ve tip basina alanlari is dosyasiyla eslestirir.
     *
     * @param  list<string|BackedEnum>  $types  secilen kapsam tipleri
     * @param  array<string, array<string, mixed>>  $rows  tip => alanlar (+ scope_file, scope_file_name)
     */
    public function sync(BusinessCase $case, array $types, array $rows): void
    {
        $selected = $this->normaliseTypes($types);

        $this->transactions->run(function () use ($case, $selected, $rows): void {
            /** @var array<string, BusinessCaseScope> $existing */
            $existing = BusinessCaseScope::query()
                ->where('business_case_id', $case->getKey())
                ->get()
                ->keyBy(static fn (BusinessCaseScope $scope): string => $scope->scope_type->value)
                ->all();

            foreach ($selected as $type) {
                $input = is_array($rows[$type] ?? null) ? $rows[$type] : [];
                $current = $existing[$type] ?? null;
                $attributes = $this->attributesFor($type, $input);

                $documentId = $this->storeScopeFile($case, $type, $current, $input);

                if ($documentId !== null) {
                    $attributes['scope_document_id'] = $documentId;
                }

                if ($current === null) {
                    $this->create([
                        ...$attributes,
                        'business_case_id' => $case->getKey(),
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
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $type = $record->getAttribute('scope_type');

        return [
            'kapsam' => $type instanceof ProjectScopeType ? $type->getLabel() : (ProjectScopeType::tryFrom((string) ($type instanceof BackedEnum ? $type->value : $type))?->getLabel() ?? (string) ($type instanceof BackedEnum ? $type->value : $type)),
            'business_case_id' => $record->getAttribute('business_case_id'),
        ];
    }

    /**
     * Filament'ten enum ya da metin olarak gelen tipleri gecerli, tekil
     * deger listesine indirger; bilinmeyen degerler yok sayilir.
     *
     * @param  list<mixed>  $types
     * @return list<string>
     */
    private function normaliseTypes(array $types): array
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
     * Tipe ait alanlari alir; digerleri yok sayilir, bos metin NULL olur.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attributesFor(string $type, array $input): array
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
     * Yuklenen kapsam listesini belgeye isler; satirin (yeni ya da mevcut)
     * belge kimligini doner, dosya yoksa mevcut baglantiyi korur.
     *
     * @param  array<string, mixed>  $input
     */
    private function storeScopeFile(BusinessCase $case, string $type, ?BusinessCaseScope $current, array $input): ?int
    {
        $tempPath = $this->firstString($input['scope_file'] ?? null);

        if ($tempPath === null) {
            return $current?->scope_document_id === null ? null : (int) $current->scope_document_id;
        }

        $originalName = $this->originalName($input['scope_file_name'] ?? null, $tempPath);

        /** @var Document|null $document */
        $document = $current?->scopeDocument;

        if ($document !== null) {
            $this->revisions->create([
                'document_id' => $document->getKey(),
                'title' => $document->title,
                'language' => 'tr',
                'purpose' => 'for_review',
                'file_temp_path' => $tempPath,
                'file_original_name' => $originalName,
            ]);

            return (int) $document->getKey();
        }

        $typeId = $this->fixedDocuments->documentTypeId(self::SCOPE_DOCUMENT_TYPE_CODE);

        if ($typeId === null) {
            throw RecordNotFoundException::make();
        }

        $document = $this->documents->createWithInitialRevision([
            'document_type_id' => $typeId,
            // documents.title 255 karakterle sinirlidir; is dosyasi basligi kirpilir.
            'title' => Str::limit((string) $case->title, 255 - mb_strlen(' – '.$suffix = ProjectScopeType::from($type)->getLabel().' kapsam listesi'), '').' – '.$suffix,
            'owner_personnel_id' => $this->actor->personnelId() ?? $case->owner_employee_id,
            'default_language' => 'tr',
            'file_temp_path' => $tempPath,
            'file_original_name' => $originalName,
        ]);

        return (int) $document->getKey();
    }

    /** Filament FileUpload tek dosyada metin, coklu dosyada dizi verir; ilk dolu yolu alir. */
    private function firstString(mixed $value): ?string
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
    private function originalName(mixed $value, string $tempPath): ?string
    {
        if (is_array($value)) {
            $value = $value[$tempPath] ?? reset($value);
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
