<?php

declare(strict_types=1);

namespace App\Query\Acquisition;

use App\Models\Acquisition\BusinessCase;

/** Is dosyasi okuma sorgulari. */
final class BusinessCaseQueries
{
    /**
     * Teklif olusturmadaki is dosyasi ozet karti icin kayit (22 Eylul 2026
     * kullanici karari): musteri, sorumlular ve kapsamlar (kapsam listesi
     * dokumaninin guncel dosyasiyla) tek seferde yuklenir.
     */
    public function forSummary(?int $id): ?BusinessCase
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        /** @var BusinessCase|null $case */
        $case = BusinessCase::query()
            ->with(['primaryParty', 'owner', 'proposalOwner', 'project', 'scopes.scopeDocument.revisions.files.fileObject'])
            ->find($id);

        return $case;
    }
}
