<?php

declare(strict_types=1);

namespace App\Query\Acquisition;

use App\Models\Acquisition\BusinessCase;
use App\Models\Party\PartyMeetingNote;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Support\Carbon;

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
     * Potansiyel isin son gorusme tarihi ("Bu is nerede?" etiketi, D-143);
     * gorusme notu - potansiyel is baglantisi (B41) yoksa bos.
     */
    public function lastMeetingOn(int $businessCaseId): ?Carbon
    {
        if (! SchemaReadiness::hasBatch('B41')) {
            return null;
        }

        $value = PartyMeetingNote::query()->where('business_case_id', $businessCaseId)->max('noted_on');

        return $value === null ? null : Carbon::parse($value);
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
