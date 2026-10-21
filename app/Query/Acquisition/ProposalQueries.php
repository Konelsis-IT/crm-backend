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
use App\Support\DisplayTime;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Teklifler listesinin sekmeleri (D-136): teklif durumuna gore suzme ve sayilar.
 */
final class ProposalQueries
{
    /** Pano uyari seridinin teklif uyarilari (D-227); Teklifler listesindeki "Uyari" suzgeci ayni sorgu. */
    public const ALERT_STALE_TO_SUBMIT = 'stale_to_submit';

    public const ALERT_STALE_SUBMITTED = 'stale_submitted';

    public const ALERT_NO_AMOUNT = 'no_amount';

    public const ALERTS = [self::ALERT_STALE_TO_SUBMIT, self::ALERT_STALE_SUBMITTED, self::ALERT_NO_AMOUNT];

    /** Verilecek teklif bu kadar gunden eskiyse uyari. */
    public const STALE_TO_SUBMIT_DAYS = 14;

    /** Verilen teklif bu kadar gundur cevapsizsa uyari. */
    public const STALE_SUBMITTED_DAYS = 30;

    public function withOfferStatus(Builder $query, OfferStatus $status): Builder
    {
        return $query->where('offer_status', $status->value);
    }

    /**
     * Uyari kapsamindaki teklifler (taslak degil, potansiyel isi olan):
     * 14 gunu gecen verilecek teklif, 30 gunden uzun cevapsiz verilen teklif
     * (guncel surumun verilis tarihi), tutari girilmemis teklif (guncel surumun
     * tutari da kapsam satis toplami da bos). Pano sayisi ve listenin suzgeci
     * bu sorguyu kullanir; ikisi hep ayni kaydi sayar.
     */
    public function applyAlert(Builder $query, string $alert): Builder
    {
        if (! in_array($alert, self::ALERTS, true)) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $today = Carbon::now(DisplayTime::zone())->startOfDay();
        $query->where($table.'.is_draft', false)->whereHas('businessCase');

        return match ($alert) {
            self::ALERT_STALE_TO_SUBMIT => $query
                ->where($table.'.offer_status', OfferStatus::ToBeSubmitted->value)
                ->where($table.'.created_at', '<', $today->copy()->subDays(self::STALE_TO_SUBMIT_DAYS)->utc()),
            self::ALERT_STALE_SUBMITTED => $query
                ->where($table.'.offer_status', OfferStatus::Submitted->value)
                ->whereHas('currentVersion', fn (Builder $version): Builder => $version
                    ->where('submitted_at', '<', $today->copy()->subDays(self::STALE_SUBMITTED_DAYS)->utc())),
            default => $query->whereRaw('COALESCE('
                .'(SELECT v.total_price FROM proposal_versions v WHERE v.id = '.$table.'.current_version_id), '
                .(SchemaReadiness::hasBatch('B43')
                    ? '(SELECT SUM(s.total_sales) FROM proposal_version_scopes s WHERE s.proposal_version_id = '.$table.'.current_version_id)'
                    : 'NULL')
                .') IS NULL'),
        };
    }

    /** @return array<string, string> Uyari => ad (Teklifler listesinin suzgeci). */
    public function alertOptions(): array
    {
        return [
            self::ALERT_STALE_TO_SUBMIT => (string) __('proposal.alerts.stale_to_submit', ['days' => self::STALE_TO_SUBMIT_DAYS]),
            self::ALERT_STALE_SUBMITTED => (string) __('proposal.alerts.stale_submitted', ['days' => self::STALE_SUBMITTED_DAYS]),
            self::ALERT_NO_AMOUNT => (string) __('proposal.alerts.no_amount'),
        ];
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
