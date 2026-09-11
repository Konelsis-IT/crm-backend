<?php

declare(strict_types=1);

namespace App\Query\Document;

use App\Models\Document\DocumentRevision;

/**
 * Belge ekranlarinin ve belge revizyonu secen diger ekranlarin okuma sorgulari.
 */
final class DocumentQueries
{
    /**
     * Belge revizyonu secim listesi; anahtar revizyon id, deger "REVİZYON KODU · baslik".
     *
     * @return array<int, string>
     */
    public function revisionOptions(): array
    {
        $options = [];

        $rows = DocumentRevision::query()
            ->orderBy('revision_code')
            ->get(['id', 'revision_code', 'title']);

        foreach ($rows as $row) {
            $options[(int) $row->id] = trim((string) $row->revision_code.' · '.(string) $row->title, ' ·');
        }

        return $options;
    }
}
