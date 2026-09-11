<?php

declare(strict_types=1);

namespace App\Query\Document;

use App\Models\Document\DocumentShare;
use App\Services\Platform\SchemaReadiness;

/**
 * Paylasim sayfasinin okuma sorgulari (D-75).
 */
final class DocumentShareQueries
{
    /** Token'a gore acik paylasim; yoksa, iptal edildiyse veya suresi dolduysa null. */
    public function openByToken(string $token): ?DocumentShare
    {
        if ($token === '' || strlen($token) > 64 || ! SchemaReadiness::hasBatch('B06A')) {
            return null;
        }

        /** @var DocumentShare|null $share */
        $share = DocumentShare::query()
            ->with(['document.documentType', 'document.currentRevision.files.fileObject'])
            ->where('token', $token)
            ->first();

        if ($share === null || ! $share->isOpen()) {
            return null;
        }

        return $share;
    }
}
