<?php

declare(strict_types=1);

namespace App\Query\Acquisition;

use App\Models\Acquisition\TenderNotice;
use App\Models\Acquisition\TenderSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Ihale ilani okuma sorgulari (B43, D-155: ihale potansiyel isten once gelir). */
final class TenderQueries
{
    /**
     * Ihale kaynaklari: id => ad.
     *
     * @return array<int, string>
     */
    public function sourceOptions(): array
    {
        return TenderSource::query()
            ->orderBy('name_tr')
            ->pluck('name_tr', 'id')
            ->all();
    }

    /**
     * Potansiyel ise baglanabilecek ihaleler: henuz baglanmamislar ve (verilirse)
     * bu ise zaten bagli olanlar. "Harici no · baslik", en yenisi ustte.
     *
     * @return array<int, string>
     */
    public function linkableOptions(?int $businessCaseId = null): array
    {
        return TenderNotice::query()
            ->where(function (Builder $query) use ($businessCaseId): void {
                $query->whereNull('business_case_id');

                if ($businessCaseId !== null && $businessCaseId > 0) {
                    $query->orWhere('business_case_id', $businessCaseId);
                }
            })
            ->orderByDesc('captured_at')
            ->limit(300)
            ->get(['id', 'title', 'external_notice_id'])
            ->mapWithKeys(static fn (TenderNotice $notice): array => [
                (int) $notice->getKey() => trim((filled($notice->external_notice_id) ? $notice->external_notice_id.' · ' : '').$notice->title),
            ])
            ->all();
    }

    /** Ozet karti icin ihale (kaynak, ilani veren, guncel surum, bagli potansiyel is). */
    public function forSummary(?int $id): ?TenderNotice
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        /** @var TenderNotice|null $notice */
        $notice = TenderNotice::query()
            ->with(['source', 'issuerParty', 'currentVersion', 'businessCase.codes'])
            ->find($id);

        return $notice;
    }

    /**
     * Potansiyel isin ihaleleri, en yenisi once.
     *
     * @return Collection<int, TenderNotice>
     */
    public function forCase(int $businessCaseId): Collection
    {
        return TenderNotice::query()
            ->with(['source', 'issuerParty', 'currentVersion'])
            ->where('business_case_id', $businessCaseId)
            ->orderByDesc('captured_at')
            ->get();
    }

    /** Taslak ihale sayisi (liste sekmesi). */
    public function draftCount(): int
    {
        return TenderNotice::query()->where('is_draft', true)->count();
    }

    public function total(): int
    {
        return TenderNotice::query()->count();
    }

    public function onlyDrafts(Builder $query): Builder
    {
        return $query->where('is_draft', true);
    }
}
