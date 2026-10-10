<?php

declare(strict_types=1);

namespace App\Query\Document;

use App\Enums\Document\DocumentStatus;
use App\Models\Document\Document;
use App\Models\Document\DocumentType;

/**
 * Sabit (bir kez yuklenen, tekliflere baglanan) belgelerin ve kodla anilan
 * dokuman turlerinin okuma sorgulari (B29, D-101).
 *
 * Referanslar belgesi (`REF`) ve Genel katalog (`KAT`) Dokumanlar'a bir kez
 * yuklenir; teklif sihirbazi "ekleyelim mi?" anahtariyla bu belgenin guncel
 * revizyonunu teklif surumune baglar. Ayni turde birden fazla belge varsa
 * en yeni olan kullanilir.
 *
 * D-176 (8 Ekim 2026, hata duzeltmesi): Dokuman ekranindan yuklenen belgenin
 * ilk revizyonu taslaktir ve `current_revision_id` ancak revizyon "yayimlandi"
 * olunca dolar. Eski sorgu yalniz yayimlanmis belgeyi aradigi icin canlida
 * yuklenen Genel katalog hic bulunmuyordu. Artik revizyonu olan, arsivlenmemis
 * / gecersiz kilinmamis / yerine baskasi gecmemis en yeni belge alinir;
 * gosterilen revizyon Document::displayRevision() (yayimlanmis, yoksa en son).
 */
final class FixedDocumentQueries
{
    private const REFERENCE_TYPE_CODE = 'REF';

    private const CATALOG_TYPE_CODE = 'KAT';

    /** Sabit / otomatik belge olarak kullanilmayan belge durumlari (D-176). */
    public const INACTIVE_STATUSES = [DocumentStatus::Archived, DocumentStatus::Obsolete, DocumentStatus::Superseded];

    /** Kodla anilan dokuman turunun kimligi; tur tanimli degilse null. */
    public function documentTypeId(string $code): ?int
    {
        $id = DocumentType::query()->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Kullanilabilir en yeni Referanslar belgesi (REF); yoksa null. */
    public function referenceDocument(): ?Document
    {
        return $this->latestOfType(self::REFERENCE_TYPE_CODE);
    }

    /** Kullanilabilir en yeni Genel katalog belgesi (KAT); yoksa null. */
    public function catalogDocument(): ?Document
    {
        return $this->latestOfType(self::CATALOG_TYPE_CODE);
    }

    /**
     * Turun kullanilabilir en yeni belgesi (D-176): revizyonu olan, durumu
     * arsiv / gecersiz / yerine gecilmis olmayan; en son acilan belge.
     */
    public function latestOfType(string $code): ?Document
    {
        $typeId = $this->documentTypeId($code);

        if ($typeId === null) {
            return null;
        }

        return Document::query()
            ->with(['documentType', 'currentRevision'])
            ->where('document_type_id', $typeId)
            ->whereNotIn('status', array_map(static fn (DocumentStatus $status): string => $status->value, self::INACTIVE_STATUSES))
            ->whereHas('revisions')
            ->orderByDesc('id')
            ->first();
    }
}
