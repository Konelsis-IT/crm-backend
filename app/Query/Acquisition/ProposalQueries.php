<?php

declare(strict_types=1);

namespace App\Query\Acquisition;

use App\Enums\Acquisition\OfferStatus;
use App\Models\Acquisition\BusinessCaseScope;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalDocument;
use App\Models\Acquisition\ProposalVersionScope;
use App\Services\Platform\SchemaReadiness;
use App\Support\Acquisition\ScopeTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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

    /**
     * Teklif surumunun belge satirlari (D-158 Dokumanlar sekmesi; D-184: tablo
     * ozel veriyle kurulur, sorgu burada). $skipRoles: otomatik belgenin yerini
     * aldigi eski roller (D-176, ayni belge iki kez gorunmesin).
     *
     * @param  list<string>  $skipRoles
     * @return EloquentCollection<int, ProposalDocument>
     */
    public function versionDocuments(?int $versionId, array $skipRoles = []): EloquentCollection
    {
        if ($versionId === null) {
            return new EloquentCollection;
        }

        return ProposalDocument::query()
            ->where('proposal_version_id', $versionId)
            ->when($skipRoles !== [], static fn (Builder $query): Builder => $query->whereNotIn('document_role', $skipRoles))
            ->with(['documentRevision.document', 'documentRevision.files.fileObject'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Teklifin proje tipleri (D-184 referans listesi satiri ve ZIP'i): B43'te
     * guncel surumun kapsamlari, oncesinde potansiyel isin kapsamlari.
     *
     * @return list<string>
     */
    public function scopeTypes(Proposal $proposal): array
    {
        if (SchemaReadiness::hasBatch('B43') && $proposal->current_version_id !== null) {
            $proposal->loadMissing('currentVersion.scopes');
            $types = $proposal->currentVersion?->scopes->map(static fn (ProposalVersionScope $scope): mixed => $scope->scope_type)->all() ?? [];

            if ($types !== []) {
                return ScopeTypes::values($types);
            }
        }

        $proposal->loadMissing('businessCase.scopes');

        return ScopeTypes::values($proposal->businessCase?->scopes->map(static fn (BusinessCaseScope $scope): mixed => $scope->scope_type)->all() ?? []);
    }
}
