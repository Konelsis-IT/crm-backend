<?php

declare(strict_types=1);

namespace App\Query\Document;

use App\Enums\Acquisition\ProposalDocumentRole;
use App\Enums\Document\DocumentRevisionFileRole;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCaseDocument;
use App\Models\Acquisition\BusinessCaseScope;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\ProposalVersionScope;
use App\Models\Acquisition\ProposalVersionScopeDocument;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Document\DocumentRevisionFile;
use App\Models\Document\FileObject;
use App\Services\Platform\SchemaReadiness;
use App\Support\Acquisition\ChecklistTemplates;
use App\Support\Acquisition\CostLists;
use BackedEnum;
use Closure;
use Filament\Support\Contracts\HasLabel;

/**
 * "Tum belgeleri indir" (D-176) icin bir kayitta birikmis belgelerin listesi:
 * her satir ZIP icindeki klasor yolu + belge + revizyon + asil dosya.
 *
 * Kurallar:
 * - Her revizyon ZIP'e bir kez girer (D-158 "bir belge iki kez gorunmez"):
 *   teklifte guncel surumun belgeleri kok klasorlerde, onceki surumlerde kalan
 *   (guncel surumde olmayan) revizyonlar "Onceki surumler/Surum N" altinda.
 * - Arsivlenmis / gecersiz / yerine gecilmis belgeler (dokuman durumu) kendi
 *   yerlerinde degil "Arsiv" klasorunde: atlanmaz ama digerleriyle karismaz.
 * - Otomatik sirket belgeleri (Genel katalog) "Genel belgeler" klasorunde;
 *   eski "Genel kataloğu ekle" satirlari otomatik belge varken atlanir.
 * - Yetki burada degil, DocumentBundleService'te (belge basina politika).
 *
 * Baska ekranlar (proje Dokumanlar sekmesi, sozlesme surumleri, ihale belgeleri,
 * is talebi ekleri) ayni satir bicimini ureten bir yontem ekleyerek ayni
 * servisi ve DocumentBundleAction'i kullanabilir.
 */
final class DocumentBundleQueries
{
    public function __construct(private readonly AutomaticDocumentQueries $automatic) {}

    /**
     * @return list<array{folders: list<string>, document: Document, revision: DocumentRevision, file: FileObject}>
     */
    public function forProposal(Proposal $proposal): array
    {
        $seen = [];
        $entries = $this->proposalEntries($proposal, [], $seen, withHistory: true);

        return [...$entries, ...$this->automaticEntries($seen)];
    }

    /**
     * Potansiyel isin belgeleri: kontrol listesi maddeleri, ek belgeler, kapsam
     * listeleri ve ($includeProposal izin verdigi) tekliflerin guncel surum
     * belgeleri; teklif varsa otomatik sirket belgeleri bir kez.
     *
     * @param  Closure(Proposal): bool  $includeProposal
     * @return list<array{folders: list<string>, document: Document, revision: DocumentRevision, file: FileObject}>
     */
    public function forBusinessCase(BusinessCase $case, Closure $includeProposal): array
    {
        $entries = [];
        $seen = [];

        if (SchemaReadiness::hasBatch('B43')) {
            $case->loadMissing('caseDocuments.document.revisions.files.fileObject');

            foreach ($case->caseDocuments->sortBy(['sort_order', 'id']) as $row) {
                /** @var BusinessCaseDocument $row */
                $folders = $row->item_code !== null
                    ? [
                        (string) __('document_bundle.folders.checklist'),
                        mb_strtoupper((string) $row->template_code),
                        $row->item_code.'. '.ChecklistTemplates::label((string) $row->template_code, (string) $row->item_code),
                    ]
                    : [(string) __('document_bundle.folders.extra')];

                $this->push($entries, $seen, $folders, $row->document, self::newestRevision($row->document));
            }
        }

        if (SchemaReadiness::hasBatch('B29')) {
            $case->loadMissing('scopes.scopeDocument.revisions.files.fileObject');

            foreach ($case->scopes as $scope) {
                /** @var BusinessCaseScope $scope */
                $this->push($entries, $seen, [(string) __('document_bundle.folders.scopes'), self::scopeLabel($scope->scope_type)], $scope->scopeDocument, self::newestRevision($scope->scopeDocument));
            }
        }

        $hasProposal = false;

        foreach ($case->proposals()->orderBy('id')->get() as $proposal) {
            /** @var Proposal $proposal */
            if (! $includeProposal($proposal)) {
                continue;
            }

            $hasProposal = true;
            $folder = trim(implode(' ', array_filter([(string) $proposal->proposal_no, (string) $proposal->title])));
            $entries = [...$entries, ...$this->proposalEntries($proposal, [(string) __('document_bundle.folders.proposals'), $folder !== '' ? $folder : (string) $proposal->getKey()], $seen, withHistory: false)];
        }

        // Otomatik belgeler yalniz kayitta baska belge varsa ve teklif varsa eklenir:
        // potansiyel isin ZIP'i yalniz katalogdan olusmasin.
        return $hasProposal && $entries !== [] ? [...$entries, ...$this->automaticEntries($seen)] : $entries;
    }

    /**
     * @param  list<string>  $prefix
     * @param  array<string, true>  $seen
     * @return list<array{folders: list<string>, document: Document, revision: DocumentRevision, file: FileObject}>
     */
    private function proposalEntries(Proposal $proposal, array $prefix, array &$seen, bool $withHistory): array
    {
        $entries = [];
        $skipRoles = $this->automatic->replacedProposalRoles();

        $versions = $proposal->versions()
            ->with([
                'documents.documentRevision.document',
                'documents.documentRevision.files.fileObject',
                ...(SchemaReadiness::hasBatch('B43') ? ['scopes.scopeDocument', 'scopes.scopeDocumentRevision.files.fileObject'] : []),
            ])
            ->orderByDesc('version_no')
            ->get();

        $current = $versions->firstWhere('id', $proposal->current_version_id) ?? $versions->first();

        if ($current === null) {
            return [];
        }

        $ordered = $withHistory ? [$current, ...$versions->reject(static fn (ProposalVersion $version): bool => $version->is($current))->all()] : [$current];

        foreach ($ordered as $version) {
            /** @var ProposalVersion $version */
            $base = $version->is($current)
                ? $prefix
                : [...$prefix, (string) __('document_bundle.folders.previous_versions'), (string) __('document_bundle.folders.version', ['no' => $version->version_no])];

            foreach ($version->documents->sortBy(['sort_order', 'id']) as $row) {
                /** @var ProposalDocument $row */
                $role = $row->document_role;
                $roleValue = $role instanceof BackedEnum ? (string) $role->value : (string) $role;

                if (in_array($roleValue, $skipRoles, true)) {
                    continue;
                }

                $label = $role instanceof HasLabel ? (string) $role->getLabel() : (string) (ProposalDocumentRole::tryFrom($roleValue)?->getLabel() ?? $roleValue);
                $this->push($entries, $seen, [...$base, $label], $row->documentRevision?->document, $row->documentRevision);
            }

            if (SchemaReadiness::hasBatch('B43')) {
                foreach ($version->scopes as $scope) {
                    /** @var ProposalVersionScope $scope */
                    $revision = $scope->scopeDocumentRevision;
                    $this->push($entries, $seen, [...$base, (string) __('document_bundle.folders.scopes'), self::scopeLabel($scope->scope_type)], $scope->scopeDocument ?? $revision?->document, $revision);

                    // D-181: maliyet listeleri "Maliyet listeleri/<Proje tipi>/" altinda.
                    if (CostLists::enabled()) {
                        foreach ($scope->costDocuments()->with(['documentRevision.document', 'documentRevision.files.fileObject'])->get() as $row) {
                            /** @var ProposalVersionScopeDocument $row */
                            $this->push($entries, $seen, [...$base, (string) __('document_bundle.folders.cost_lists'), self::scopeLabel($scope->scope_type)], $row->documentRevision?->document, $row->documentRevision);
                        }
                    }
                }
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, true>  $seen
     * @return list<array{folders: list<string>, document: Document, revision: DocumentRevision, file: FileObject}>
     */
    private function automaticEntries(array &$seen): array
    {
        $entries = [];

        foreach ($this->automatic->forProposals() as $automatic) {
            if (isset($seen['d'.$automatic['document']->getKey()])) {
                continue;
            }

            $this->push($entries, $seen, [(string) __('document_bundle.folders.general')], $automatic['document'], $automatic['revision']);
        }

        return $entries;
    }

    /**
     * Satiri ekler; ayni revizyon daha once eklendiyse ya da asil dosya yoksa atlar.
     *
     * @param  list<array{folders: list<string>, document: Document, revision: DocumentRevision, file: FileObject}>  $entries
     * @param  array<string, true>  $seen
     * @param  list<string>  $folders
     */
    private function push(array &$entries, array &$seen, array $folders, ?Document $document, ?DocumentRevision $revision): void
    {
        if ($document === null || $revision === null || isset($seen['r'.$revision->getKey()])) {
            return;
        }

        $file = self::originalFile($revision);

        if ($file === null) {
            return;
        }

        $seen['r'.$revision->getKey()] = true;
        $seen['d'.$document->getKey()] = true;

        if (in_array($document->status, FixedDocumentQueries::INACTIVE_STATUSES, true)) {
            $folders = [(string) __('document_bundle.folders.archive'), ...$folders];
        }

        $entries[] = ['folders' => $folders, 'document' => $document, 'revision' => $revision, 'file' => $file];
    }

    private static function originalFile(DocumentRevision $revision): ?FileObject
    {
        $revision->loadMissing('files.fileObject');

        $file = $revision->files
            ->first(static fn (DocumentRevisionFile $row): bool => $row->file_role === DocumentRevisionFileRole::Original)
            ?->fileObject;

        return $file instanceof FileObject ? $file : null;
    }

    /** Ekranlardaki gibi (DocumentLine) belgenin en yeni revizyonu. */
    private static function newestRevision(?Document $document): ?DocumentRevision
    {
        /** @var DocumentRevision|null $revision */
        $revision = $document?->revisions->sortByDesc('revision_no')->first();

        return $revision;
    }

    private static function scopeLabel(mixed $type): string
    {
        if ($type instanceof HasLabel) {
            return (string) $type->getLabel();
        }

        return $type instanceof BackedEnum ? (string) $type->value : (string) $type;
    }
}
