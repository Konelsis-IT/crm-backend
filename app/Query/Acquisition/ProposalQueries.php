<?php

declare(strict_types=1);

namespace App\Query\Acquisition;

use App\Enums\Acquisition\OfferStatus;
use App\Models\Acquisition\Proposal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Teklifler listesinin sekmeleri (D-136): teklif durumuna gore suzme ve sayilar.
 */
final class ProposalQueries
{
    public function withOfferStatus(Builder $query, OfferStatus $status): Builder
    {
        return $query->where('offer_status', $status->value);
    }

    /**
     * Teklif durumu basina teklif sayisi; tek sorguda toplanir.
     *
     * @return array<string, int>
     */
    public function offerStatusCounts(): array
    {
        return Proposal::query()
            ->whereNotNull('offer_status')
            ->toBase()
            ->selectRaw('offer_status, COUNT(*) as aggregate')
            ->groupBy('offer_status')
            ->pluck('aggregate', 'offer_status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    public function total(): int
    {
        return Proposal::query()->count();
    }

    /**
     * Bir potansiyel isin teklifleri (gorusme notu baglantisi, D-137): id => "TKLF-... · baslik".
     *
     * @return array<int, string>
     */
    public function optionsForCase(?int $businessCaseId): array
    {
        if ($businessCaseId === null || $businessCaseId <= 0) {
            return [];
        }

        return Proposal::query()
            ->where('business_case_id', $businessCaseId)
            ->orderBy('proposal_no')
            ->get(['id', 'proposal_no', 'title'])
            ->mapWithKeys(static fn (Proposal $proposal): array => [(int) $proposal->getKey() => $proposal->proposal_no.' · '.$proposal->title])
            ->all();
    }
}
