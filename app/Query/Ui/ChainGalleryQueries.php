<?php

declare(strict_types=1);

namespace App\Query\Ui;

use App\Models\Acquisition\Proposal;
use Illuminate\Support\Facades\DB;

/**
 * UI Deneme > Adim denemeleri (D-140): "Potansiyel is -> Teklif -> Proje"
 * zincirinin gosterim denemeleri icin ornek teklif. Salt okuma.
 */
final class ChainGalleryQueries
{
    /**
     * Istenen teklif; yoksa birden fazla teklifi olan bir potansiyel isin
     * teklifi (kardes teklifler gorunsun), o da yoksa en son teklif.
     */
    public function proposal(?int $requestedId): ?Proposal
    {
        $with = [
            'businessCase.codes',
            'businessCase.primaryParty',
            'businessCase.owner',
            'businessCase.scopes',
            'businessCase.project.businessCode',
            'businessCase.project.projectManager',
            'businessCase.proposals.currentVersion',
            'currentVersion',
            'owner',
        ];

        if ($requestedId !== null && $requestedId > 0) {
            $proposal = Proposal::query()->with($with)->find($requestedId);

            if ($proposal instanceof Proposal) {
                return $proposal;
            }
        }

        $caseWithSiblings = Proposal::query()
            ->select('business_case_id')
            ->groupBy('business_case_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc(DB::raw('MAX(id)'))
            ->value('business_case_id');

        /** @var Proposal|null $proposal */
        $proposal = Proposal::query()
            ->with($with)
            ->when($caseWithSiblings !== null, fn ($query) => $query->where('business_case_id', $caseWithSiblings))
            ->orderByDesc('id')
            ->first();

        return $proposal;
    }

    /**
     * Ornek teklif secimi: id => "TKLF-... · baslik", en yenisi ustte.
     *
     * @return array<int, string>
     */
    public function options(int $limit = 300): array
    {
        return Proposal::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'proposal_no', 'title'])
            ->mapWithKeys(static fn (Proposal $proposal): array => [(int) $proposal->getKey() => $proposal->proposal_no.' · '.$proposal->title])
            ->all();
    }
}
