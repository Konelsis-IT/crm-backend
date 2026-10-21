<?php

declare(strict_types=1);

namespace App\Query\Document;

use App\Enums\Document\DocumentRevisionFileRole;
use App\Enums\Document\DocumentStatus;
use App\Models\Document\Document;
use App\Query\Ui\NewDashboardQueries;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ust cubuktaki Sablonlar menusunun sorgulari (D-226): sablon turundeki
 * (`SAB`, D-208) etkin belgeler - kisinin departmanlarinin ve sahip birimi
 * bos olan ortak sablonlar. Gosterilen revizyonun orijinal dosyasi onceden
 * yuklenir; menu her sayfada cizildigi icin belge basina sorgu acilmaz.
 */
final class TemplateDocumentQueries
{
    public function __construct(
        private readonly FixedDocumentQueries $fixed,
        private readonly NewDashboardQueries $dashboard,
    ) {}

    /**
     * Verilen birimlerin ve ortak (birimsiz) sablonlar, ada gore.
     *
     * @param  list<int>  $unitIds
     * @return Collection<int, Document>
     */
    public function templates(array $unitIds): Collection
    {
        return Document::query()
            ->with([
                'currentRevision.files' => fn ($files) => $files
                    ->where('file_role', DocumentRevisionFileRole::Original->value)
                    ->with('fileObject'),
            ])
            ->whereHas('documentType', fn ($type) => $type->where('code', NewDashboardQueries::TEMPLATE_DOCUMENT_TYPE))
            ->whereNotIn('status', array_map(static fn (DocumentStatus $status): string => $status->value, FixedDocumentQueries::INACTIVE_STATUSES))
            ->whereHas('revisions')
            ->where(fn ($query) => $query
                ->whereNull('owner_org_unit_id')
                ->when($unitIds !== [], fn ($query) => $query->orWhereIn('owner_org_unit_id', $unitIds)))
            ->orderBy('title')
            ->limit(100)
            ->get();
    }

    /**
     * Is Gelistirme ve Teklif'in standart belgeleri (D-212: katalog, referans,
     * kalite, ISG, devreye alma); panodaki sablon alanindaki gibi.
     *
     * @return \Illuminate\Support\Collection<int, Document>
     */
    public function standardDocuments(): \Illuminate\Support\Collection
    {
        return $this->dashboard->standardDocuments();
    }

    /** Sablon dokuman turunun kimligi ("+ Sablon ekle" formu icin); tur yoksa null. */
    public function templateTypeId(): ?int
    {
        return $this->fixed->documentTypeId(NewDashboardQueries::TEMPLATE_DOCUMENT_TYPE);
    }
}
