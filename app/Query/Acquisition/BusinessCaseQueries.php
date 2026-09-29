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

    /**
     * Bir tarafin musteri oldugu potansiyel isler (gorusme notu baglantisi,
     * D-137): id => "POTIS-... · baslik", en yenisi ustte.
     *
     * @return array<int, string>
     */
    public function optionsForParty(int $partyId): array
    {
        return BusinessCase::query()
            ->with('codes')
            ->where('primary_party_id', $partyId)
            ->orderByDesc('id')
            ->get(['id', 'title', 'sequence_no'])
            ->mapWithKeys(static fn (BusinessCase $case): array => [
                (int) $case->getKey() => trim(($case->caseCode()?->formatted_code ?? '').' · '.$case->title, ' ·'),
            ])
            ->all();
    }
}
