<?php

declare(strict_types=1);

namespace App\Query\Document;

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
 */
final class FixedDocumentQueries
{
    private const REFERENCE_TYPE_CODE = 'REF';

    private const CATALOG_TYPE_CODE = 'KAT';

    /** Kodla anilan dokuman turunun kimligi; tur tanimli degilse null. */
    public function documentTypeId(string $code): ?int
    {
        $id = DocumentType::query()->where('code', $code)->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Guncel revizyonu olan en yeni Referanslar belgesi (REF); yoksa null. */
    public function referenceDocument(): ?Document
    {
        return $this->latestOfType(self::REFERENCE_TYPE_CODE);
    }

    /** Guncel revizyonu olan en yeni Genel katalog belgesi (KAT); yoksa null. */
    public function catalogDocument(): ?Document
    {
        return $this->latestOfType(self::CATALOG_TYPE_CODE);
    }

    private function latestOfType(string $code): ?Document
    {
        $typeId = $this->documentTypeId($code);

        if ($typeId === null) {
            return null;
        }

        return Document::query()
            ->where('document_type_id', $typeId)
            ->whereNotNull('current_revision_id')
            ->orderByDesc('id')
            ->first();
    }
}
