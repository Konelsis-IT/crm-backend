<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Exceptions\RecordNotFoundException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCaseDocument;
use App\Models\Document\Document;
use App\Query\Document\FixedDocumentQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentRevisionService;
use App\Services\Document\DocumentService;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Acquisition\ChecklistTemplates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Potansiyel isin belgeleri (B43, D-155). Belgeler Dokumanlar'da `PIB`
 * (Potansiyel Is Belgesi) turundedir; business_case_documents satiri belgeyi
 * potansiyel ise baglar.
 *
 * - Kontrol listesi ana maddesinin belgesi (or. Cagri mektubu): madde basina
 *   tek belge. Ilk yukleme yeni belge + ilk revizyon, sonraki yuklemeler ayni
 *   belgenin yeni revizyonudur; eski dosya silinmez (gecmis korunur).
 * - Genel belgeler: her yukleme ayri belgedir.
 * - D-176: potansiyel is sayfasindaki "Belge ekle" ile bir maddeye birden fazla
 *   belge eklenebilir (addDocuments); tahtadaki belge dugmesi maddenin ilk
 *   belgesine yeni revizyon yuklemeye devam eder.
 *
 * Satirlar silinmez; belgeyi kaldirmak Dokumanlar'in isidir.
 */
final class BusinessCaseDocumentService extends AbstractService
{
    protected string $model = BusinessCaseDocument::class;

    protected string $orderBy = 'sort_order';

    /** Potansiyel is belgelerinin dokuman turu kodu. */
    private const DOCUMENT_TYPE_CODE = 'PIB';

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
     * Sihirbazdan gelen belgeler: `checklist.{sablon}.doc_{madde}` (+ `_name`)
     * ve genel belgeler (coklu yukleme, yol => ozgun ad).
     *
     * @param  array<string, mixed>  $checklist
     */
    public function syncFromForm(BusinessCase $case, array $checklist, mixed $generalFiles = null, mixed $generalNames = null): void
    {
        $this->transactions->run(function () use ($case, $checklist, $generalFiles, $generalNames): void {
            foreach (ChecklistTemplates::codes() as $template) {
                $input = $checklist[$template] ?? null;

                if (! is_array($input)) {
                    continue;
                }

                foreach (ChecklistTemplates::itemCodes($template) as $item) {
                    $key = ChecklistTemplates::documentKey($item);
                    $tempPath = self::firstString($input[$key] ?? null);

                    if ($tempPath !== null) {
                        $this->storeItemDocument($case, $template, $item, $tempPath, self::originalName($input[$key.'_name'] ?? null, $tempPath));
                    }
                }
            }

            foreach (self::paths($generalFiles) as $tempPath) {
                $this->storeGeneralDocument($case, $tempPath, self::originalName($generalNames, $tempPath));
            }
        });
    }

    /** Ana maddenin belgesi: varsa ayni belgeye yeni revizyon, yoksa yeni belge. */
    public function storeItemDocument(BusinessCase $case, string $template, string $item, string $tempPath, ?string $originalName): BusinessCaseDocument
    {
        return $this->transactions->run(function () use ($case, $template, $item, $tempPath, $originalName): BusinessCaseDocument {
            /** @var BusinessCaseDocument|null $current */
            $current = BusinessCaseDocument::query()
                ->with('document')
                ->where('business_case_id', $case->getKey())
                ->where('template_code', $template)
                ->where('item_code', $item)
                // D-176: maddede birden fazla belge olabilir; tahtadaki ilk belge (iliski sirasi).
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            /** @var Document|null $document */
            $document = $current?->document;

            if ($current !== null && $document !== null) {
                $this->revisions->create([
                    'document_id' => $document->getKey(),
                    'title' => $document->title,
                    'language' => 'tr',
                    'purpose' => 'for_review',
                    'file_temp_path' => $tempPath,
                    'file_original_name' => $originalName,
                ]);
                $this->recordActivity($current, 'revised', ['liste' => $template, 'madde' => $item, 'document_id' => $document->getKey()]);

                return $current;
            }

            $document = $this->newDocument($case, ChecklistTemplates::label($template, $item), $tempPath, $originalName);

            /** @var BusinessCaseDocument $row */
            $row = $this->create([
                'business_case_id' => $case->getKey(),
                'document_id' => $document->getKey(),
                'template_code' => $template,
                'item_code' => $item,
                'sort_order' => $this->nextSortOrder($case),
            ]);

            return $row;
        });
    }

    public function storeGeneralDocument(BusinessCase $case, string $tempPath, ?string $originalName): BusinessCaseDocument
    {
        return $this->transactions->run(function () use ($case, $tempPath, $originalName): BusinessCaseDocument {
            $suffix = filled($originalName) ? pathinfo((string) $originalName, PATHINFO_FILENAME) : (string) __('business_case.fields.case_documents');
            $document = $this->newDocument($case, $suffix !== '' ? $suffix : (string) __('business_case.fields.case_documents'), $tempPath, $originalName);

            /** @var BusinessCaseDocument $row */
            $row = $this->create([
                'business_case_id' => $case->getKey(),
                'document_id' => $document->getKey(),
                'sort_order' => $this->nextSortOrder($case),
            ]);

            return $row;
        });
    }

    /**
     * Potansiyel is sayfasindaki "Belge ekle" (D-176, 8 Ekim 2026 kullanici
     * talimati: "birden fazla yuklenebilir hem teklif hem potansiyel is
     * tarafinda"): her dosya ayri yeni belge olur. $template + $item verilirse
     * belge o kontrol listesi maddesine baglanir (maddenin ilk belgesi tahtada,
     * digerleri Belgeler kartinda gorunur); verilmezse ek belgedir. Madde
     * belgesi eklendiyse sicaklik yeniden hesaplanir (belge maddenin payidir).
     *
     * @param  list<array{path: string, name: string|null}>  $files
     * @return list<BusinessCaseDocument>
     */
    public function addDocuments(BusinessCase $case, ?string $template, ?string $item, array $files): array
    {
        $isItem = $template !== null && $item !== null;

        if ($isItem && ! in_array($item, ChecklistTemplates::itemCodes($template), true)) {
            throw RecordNotFoundException::make();
        }

        return $this->transactions->run(function () use ($case, $template, $item, $files, $isItem): array {
            $rows = [];

            foreach ($files as $file) {
                $path = trim((string) ($file['path'] ?? ''));

                if ($path === '') {
                    continue;
                }

                $name = $file['name'] ?? null;

                if ($isItem) {
                    $document = $this->newDocument($case, ChecklistTemplates::label((string) $template, (string) $item), $path, $name);

                    /** @var BusinessCaseDocument $row */
                    $row = $this->create([
                        'business_case_id' => $case->getKey(),
                        'document_id' => $document->getKey(),
                        'template_code' => $template,
                        'item_code' => $item,
                        'sort_order' => $this->nextSortOrder($case),
                    ]);
                    $rows[] = $row;

                    continue;
                }

                $rows[] = $this->storeGeneralDocument($case, $path, $name);
            }

            if ($isItem && $rows !== []) {
                app(BusinessCaseService::class)->refreshChecklistState($case);
            }

            return $rows;
        });
    }

    /**
     * Ana maddelerin belge durumu: sablon => madde => belge var mi.
     *
     * @return array<string, array<string, bool>>
     */
    public function itemPresence(BusinessCase $case): array
    {
        $presence = [];

        foreach ($case->caseDocuments()->whereNotNull('item_code')->get() as $row) {
            /** @var BusinessCaseDocument $row */
            $presence[(string) $row->template_code][(string) $row->item_code] = true;
        }

        return $presence;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return [
            'business_case_id' => $record->getAttribute('business_case_id'),
            'document_id' => $record->getAttribute('document_id'),
            'liste' => $record->getAttribute('template_code'),
            'madde' => $record->getAttribute('item_code'),
        ];
    }

    private function newDocument(BusinessCase $case, string $suffix, string $tempPath, ?string $originalName): Document
    {
        $typeId = $this->fixedDocuments->documentTypeId(self::DOCUMENT_TYPE_CODE) ?? throw RecordNotFoundException::make();

        return $this->documents->createWithInitialRevision([
            'document_type_id' => $typeId,
            // documents.title 255 karakterle sinirlidir; potansiyel is basligi kirpilir.
            'title' => Str::limit((string) $case->title, 255 - mb_strlen(' – '.$suffix), '').' – '.$suffix,
            'owner_personnel_id' => $this->actor->personnelId() ?? $case->owner_employee_id,
            'default_language' => 'tr',
            'file_temp_path' => $tempPath,
            'file_original_name' => $originalName,
        ]);
    }

    private function nextSortOrder(BusinessCase $case): int
    {
        return (int) $case->caseDocuments()->max('sort_order') + 1;
    }

    /** Filament FileUpload tek dosyada metin, coklu dosyada dizi verir; ilk dolu yolu alir. */
    private static function firstString(mixed $value): ?string
    {
        $paths = self::paths($value);

        return $paths[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private static function paths(mixed $value): array
    {
        $paths = [];

        foreach (is_array($value) ? $value : [$value] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $paths[] = trim($candidate);
            }
        }

        return $paths;
    }

    /** storeFileNamesIn tek dosyada metin, coklu dosyada yol => ad dizisi verir. */
    private static function originalName(mixed $value, string $tempPath): ?string
    {
        if (is_array($value)) {
            $value = $value[$tempPath] ?? null;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
