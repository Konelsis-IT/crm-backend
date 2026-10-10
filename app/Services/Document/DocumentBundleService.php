<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\Platform\Feature;
use App\Exceptions\Document\BundleEmptyException;
use App\Filament\Exports\ReferenceWorkbook;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\ProjectReference;
use App\Models\Acquisition\Proposal;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Document\FileObject;
use App\Models\Personnel\Personnel;
use App\Query\Acquisition\ProjectReferenceQueries;
use App\Query\Acquisition\ProposalQueries;
use App\Query\Document\DocumentBundleQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Documents\DocumentZipWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * "Tum belgeleri indir" (D-176, 8 Ekim 2026 kullanici talimati: "detay ekraninda
 * toplu tum belgeleri indir secenegi olmalidir ... klasorlu sekilde
 * karismayacak sekilde indirebilmelidir").
 *
 * Kaydin belgeleri DocumentBundleQueries'ten gelir; burada belge basina gorme
 * yetkisi (DocumentPolicy::view) suzulur, ZIP gecici dosyaya yazilir
 * (DocumentZipWriter) ve indirme Personel Hareketleri'ne kaydin uzerinde
 * "{tur}.documents_downloaded" olarak yazilir. Gecici dosyayi yanit
 * gonderildikten sonra denetleyici siler; yarim kalanlar 6 saat sonra temizlenir.
 *
 * Yeni bir ekran icin: DocumentBundleQueries'e o kaydin satirlarini ureten bir
 * yontem, buraya ince bir giris yontemi ve DocumentBundleController'a rota.
 */
final class DocumentBundleService extends AbstractService
{
    protected string $model = Document::class;

    /** Gecici ZIP klasoru (storage/app altinda). */
    private const TEMP_DIRECTORY = 'app/document-bundles-tmp';

    /** Yarim kalan gecici ZIP'lerin silinme yasi (saniye). */
    private const STALE_AFTER = 6 * 3600;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly DocumentBundleQueries $queries,
        private readonly DocumentZipWriter $writer,
        private readonly ProposalQueries $proposals,
        private readonly ProjectReferenceQueries $references,
        private readonly ReferenceWorkbook $referenceWorkbook,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /** Teklifte kisinin indirebilecegi belge var mi (dugmenin gorunurlugu). */
    public function proposalHasDocuments(Proposal $proposal, Personnel $viewer): bool
    {
        return $this->visible($this->queries->forProposal($proposal), $viewer) !== []
            || $this->referenceTypes($proposal, $viewer) !== [];
    }

    /** Potansiyel iste kisinin indirebilecegi belge var mi. */
    public function businessCaseHasDocuments(BusinessCase $case, Personnel $viewer): bool
    {
        return $this->visible($this->caseEntries($case, $viewer), $viewer) !== [];
    }

    /**
     * @return array{path: string, name: string, count: int}
     */
    public function forProposal(Proposal $proposal, Personnel $viewer): array
    {
        $entries = $this->visible($this->queries->forProposal($proposal), $viewer);
        $generated = [];

        // D-184: teklif Dokumanlar tablosundaki "Referans listesi" satiri ZIP'te de
        // "Genel belgeler" klasorunde: teklifin proje tiplerinin referans Excel'i
        // (kullanicinin bicimi, ReferenceWorkbook). Gecici dosya ZIP'ten sonra silinir.
        $types = $this->referenceTypes($proposal, $viewer);

        if ($types !== []) {
            $path = $this->referenceWorkbook->write($this->references->forExport($types), $types);
            $generated[] = $path;
            $entries[] = [
                'folders' => [(string) __('document_bundle.folders.general')],
                'path' => $path,
                'name' => ReferenceWorkbook::fileName($types),
            ];
        }

        try {
            return $this->build($entries, [(string) $proposal->proposal_no, (string) $proposal->title], 'proposal', $proposal);
        } finally {
            foreach ($generated as $path) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array{path: string, name: string, count: int}
     */
    public function forBusinessCase(BusinessCase $case, Personnel $viewer): array
    {
        $entries = $this->visible($this->caseEntries($case, $viewer), $viewer);

        return $this->build($entries, [(string) ($case->caseCode()?->formatted_code ?? ''), (string) $case->title], 'business_case', $case);
    }

    /**
     * @return list<array{folders: list<string>, document: Document, revision: DocumentRevision, file: FileObject}>
     */
    private function caseEntries(BusinessCase $case, Personnel $viewer): array
    {
        return $this->queries->forBusinessCase($case, static fn (Proposal $proposal): bool => Gate::forUser($viewer)->allows('view', $proposal));
    }

    /**
     * D-184: ZIP'e girecek referans Excel'inin proje tipleri (teklif Dokumanlar
     * tablosundaki "Referans listesi" satiriyla ayni kosullar: Referanslar, Excel
     * ve teklif referanslari ozellikleri acik, kisi referanslari gorebilir, teklifin
     * tiplerinde arsivde olmayan referans var). Uygun degilse bos liste.
     *
     * @return list<string>
     */
    private function referenceTypes(Proposal $proposal, Personnel $viewer): array
    {
        if (! SchemaReadiness::hasBatch('B50')
            || ! FeatureFlags::enabled(Feature::ReferenceExcel)
            || ! FeatureFlags::enabled(Feature::ProposalReferences)
            || ! Gate::forUser($viewer)->allows('viewAny', ProjectReference::class)) {
            return [];
        }

        $counts = $this->references->countsByType($this->proposals->scopeTypes($proposal));

        return array_keys(array_filter($counts, static fn (int $count): bool => $count > 0));
    }

    /**
     * @param  list<array{folders: list<string>, document: Document, revision: DocumentRevision, file: FileObject}>  $entries
     * @return list<array{folders: list<string>, document: Document, revision: DocumentRevision, file: FileObject}>
     */
    private function visible(array $entries, Personnel $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(array_filter($entries, static fn (array $entry): bool => $gate->allows('view', $entry['document'])));
    }

    /**
     * @param  list<array{folders: list<string>, document?: Document, revision?: DocumentRevision, file?: FileObject, path?: string, name?: string}>  $entries
     * @param  list<string>  $nameParts
     * @return array{path: string, name: string, count: int}
     */
    private function build(array $entries, array $nameParts, string $subjectType, Model $subject): array
    {
        if ($entries === []) {
            throw BundleEmptyException::make();
        }

        $directory = storage_path(self::TEMP_DIRECTORY);
        File::ensureDirectoryExists($directory);
        $this->removeStale($directory);

        $path = $directory.DIRECTORY_SEPARATOR.Str::uuid()->toString().'.zip';
        $count = $this->writer->write($entries, $path);

        if ($count === 0 || ! is_file($path)) {
            @unlink($path);

            throw BundleEmptyException::make();
        }

        $this->transactions->run(function () use ($subjectType, $subject, $count): void {
            $this->activities->record(new ActivityInput(
                subjectType: $subjectType,
                subjectId: (int) $subject->getKey(),
                actionCode: $subjectType.'.documents_downloaded',
                changes: ['dosya_sayisi' => $count],
            ));
        });

        $title = trim(implode(' ', array_filter($nameParts, static fn (string $part): bool => trim($part) !== '')));
        $name = DocumentZipWriter::segment(trim($title.' '.__('document_bundle.file_suffix'))).'.zip';

        return ['path' => $path, 'name' => $name, 'count' => $count];
    }

    private function removeStale(string $directory): void
    {
        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.zip') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - self::STALE_AFTER) {
                @unlink($file);
            }
        }
    }
}
