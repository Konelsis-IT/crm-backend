<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Acquisition\ProposalDocumentRole;
use App\Filament\Exports\ReferenceWorkbook;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Query\Acquisition\ProjectReferenceQueries;
use App\Query\Acquisition\ProposalQueries;
use App\Query\Document\AutomaticDocumentQueries;
use Filament\Support\ArrayRecord;
use Illuminate\Support\Collection;

/**
 * Teklif Dokumanlar tablosunun satirlari (D-184, 9 Ekim 2026 kullanici
 * talimati: "Teklif duzenle dedim, Dokumanlar kismina tikladim; burada Genel
 * katalog ve Referans listesi belge olarak gorunmuyor, onlarin da gorunmesi
 * gerekiyor").
 *
 * Tablo Filament'in ozel veri destegiyle (Table::records) kurulur: guncel
 * surumun ProposalDocument modelleri ve onlarin arkasinda iki sanal satir.
 * Sanal satirlar teklife kopyalanmaz, veritabaninda teklif basina satirlari
 * yoktur ("her teklif icin o belgeyi tekrar sistemde cogaltma yapmayacak,
 * sadece veritabanindan iliski"); her istekte yeniden hesaplanir:
 *
 * - Genel katalog: Dokumanlar'daki en yeni KAT (AutomaticDocumentQueries,
 *   ozellik acquisition.proposals.automatic_documents). Satira ya da indir
 *   simgesine tiklamak PDF'i yeni sekmede satir ici acar.
 * - Referans listesi: teklifin proje tiplerinin yapisal referanslari (D-177,
 *   project_references; ReferenceListField::enabled). "GES · 166 referans"
 *   bicimi; satira ya da indir simgesine tiklamak o tiplerin Excel'ini
 *   kullanicinin bicimiyle indirir (ReferenceTable::downloadFor).
 *
 * Sanal satir dizisi ProposalDocument'in sutun yollarini taklit eder
 * (document_role, documentRevision.document.document_no, documentRevision.revision_code,
 * documentRevision.created_at); boylece tablo sutunlari iki tur satir icin aynidir.
 */
final class ProposalDocumentRows
{
    public const CATALOG = 'general-catalog';

    public const REFERENCES = 'reference-list';

    /**
     * Guncel surumun belgeleri + sanal satirlar (sira: belgeler, katalog, referanslar).
     *
     * @return Collection<int|string, ProposalDocument|array<string, mixed>>
     */
    public function forProposal(Proposal $proposal): Collection
    {
        $automatic = app(AutomaticDocumentQueries::class);
        $versionId = $proposal->current_version_id !== null ? (int) $proposal->current_version_id : null;

        /** @var list<ProposalDocument|array<string, mixed>> $rows */
        $rows = app(ProposalQueries::class)->versionDocuments($versionId, $automatic->replacedProposalRoles())->all();

        foreach ($automatic->forProposals() as $entry) {
            $info = DocumentLine::info($entry['document'], $entry['revision']);

            $rows[] = [
                ArrayRecord::getKeyName() => self::CATALOG.'-'.mb_strtolower($entry['code']),
                'virtual' => self::CATALOG,
                'document_role' => ProposalDocumentRole::Catalog,
                'documentRevision' => [
                    'document' => [
                        'document_no' => $entry['document']->document_no,
                        'title' => $entry['document']->title,
                    ],
                    'revision_code' => $entry['revision']->revision_code,
                    'created_at' => $entry['revision']->created_at,
                ],
                'file' => $info['file'] ?? $entry['file']->original_name,
                'url' => FileLinks::revisionOriginal($entry['revision'], 'inline'),
                // D-186: satirdaki indir simgesi (satir ici acmanin yaninda).
                'download_url' => FileLinks::revisionOriginal($entry['revision'], 'download'),
            ];
        }

        if (($references = $this->referenceRow($proposal)) !== null) {
            $rows[] = $references;
        }

        return collect($rows);
    }

    /** Sanal satir mi (dizi kayit)? Turu: self::CATALOG / self::REFERENCES. */
    public static function kind(mixed $record): ?string
    {
        return is_array($record) ? ($record['virtual'] ?? null) : null;
    }

    /**
     * Referans listesi satiri; ozellik kapaliysa, teklifin proje tipi yoksa ya
     * da bu tiplerde arsivde olmayan referans yoksa null.
     *
     * @return array<string, mixed>|null
     */
    private function referenceRow(Proposal $proposal): ?array
    {
        if (! ReferenceListField::enabled()) {
            return null;
        }

        $types = app(ProposalQueries::class)->scopeTypes($proposal);
        $counts = array_filter(app(ProjectReferenceQueries::class)->countsByType($types), static fn (int $count): bool => $count > 0);

        if ($counts === []) {
            return null;
        }

        $summary = [];

        foreach ($counts as $type => $count) {
            $summary[] = __('proposal_document.virtual.reference_count', [
                'type' => (string) ProjectScopeType::from($type)->getLabel(),
                'count' => $count,
            ]);
        }

        $wanted = array_keys($counts);

        return [
            ArrayRecord::getKeyName() => self::REFERENCES,
            'virtual' => self::REFERENCES,
            'document_role' => (string) __('proposal_document.virtual.reference_list'),
            'documentRevision' => [
                'document' => [
                    'document_no' => implode(', ', $summary),
                    'title' => null,
                ],
                'revision_code' => null,
                'created_at' => null,
            ],
            'file' => ReferenceTable::excelEnabled() ? ReferenceWorkbook::fileName($wanted) : null,
            'url' => null,
            'reference_types' => $wanted,
        ];
    }
}
