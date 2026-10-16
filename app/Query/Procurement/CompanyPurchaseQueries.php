<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Procurement\PurchaseNeedSource;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseRequestStatus;
use App\Enums\Procurement\UsageArea;
use App\Models\Procurement\CompanyPurchase;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuote;
use App\Models\Procurement\PurchaseRequest;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use App\Support\Money;
use App\Support\Procurement\UnitResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sirket ici alimlar okumalari (B52, D-189): sekmeler (Tumu / Yenilemesi
 * yaklasan / Yenileme tarihi gecmis / Yenilenmeyen), arsiv suzgeci ve
 * gelecek giderler (yenileme donemleriyle onumuzdeki aylarin tahmini gideri,
 * para birimine gore).
 */
final class CompanyPurchaseQueries
{
    public const TAB_ALL = 'all';

    public const TAB_UPCOMING = 'upcoming';

    public const TAB_OVERDUE = 'overdue';

    public const TAB_ONE_TIME = 'one_time';

    /** Yaklasan yenileme penceresi (gun). */
    public const UPCOMING_DAYS = 60;

    public function tabScope(Builder $query, string $tab): Builder
    {
        $today = $this->today();

        return match ($tab) {
            self::TAB_UPCOMING => $query->whereNotNull('next_renewal_on')
                ->whereDate('next_renewal_on', '>=', $today)
                ->whereDate('next_renewal_on', '<=', Carbon::parse($today)->addDays(self::UPCOMING_DAYS)->toDateString()),
            self::TAB_OVERDUE => $query->whereNotNull('next_renewal_on')->whereDate('next_renewal_on', '<', $today)
                ->where(fn (Builder $end): Builder => $end->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today)),
            self::TAB_ONE_TIME => $query->whereNull('renewal_months'),
            default => $query,
        };
    }

    public function archiveScope(Builder $query, string $mode): Builder
    {
        if (! SchemaReadiness::hasBatch('B52')) {
            return $query;
        }

        return match ($mode) {
            'archived' => $query->whereNotNull($query->qualifyColumn('archived_at')),
            'all' => $query,
            default => $query->whereNull($query->qualifyColumn('archived_at')),
        };
    }

    /**
     * Onumuzdeki N ayin yenileme giderleri (para birimi => tutar): her
     * yenilenen alim, bitis tarihine kadar her donemde bir kez sayilir.
     *
     * @return array<string, float>
     */
    public function projectedRenewals(int $months = 12): array
    {
        $from = Carbon::parse($this->today());
        $until = $from->copy()->addMonthsNoOverflow($months);
        $totals = [];

        $purchases = CompanyPurchase::query()
            ->notArchived()
            ->whereNotNull('renewal_months')
            ->whereNotNull('next_renewal_on')
            ->get(['id', 'quantity', 'unit_price', 'currency_code', 'renewal_months', 'next_renewal_on', 'ends_on']);

        foreach ($purchases as $purchase) {
            /** @var CompanyPurchase $purchase */
            $period = (int) $purchase->getAttribute('renewal_months');
            $date = $purchase->getAttribute('next_renewal_on')?->copy();
            $ends = $purchase->getAttribute('ends_on');
            $currency = (string) $purchase->getAttribute('currency_code');

            while ($date !== null && $period > 0 && $date->lessThan($until)) {
                if ($ends !== null && $date->greaterThan($ends)) {
                    break;
                }

                if ($date->greaterThanOrEqualTo($from)) {
                    $totals[$currency] = ($totals[$currency] ?? 0.0) + $purchase->totalAmount();
                }

                $date = $date->addMonthsNoOverflow($period);
            }
        }

        ksort($totals);

        return $totals;
    }

    /**
     * Sirket ici alimin baglanacagi satin alma talepleri (D-190): kullanim yeri
     * Sirket ici ya da kaynagi satin alma talebi olan, iptal edilmemis, katalog
     * kalemi bagli ve henuz sirket ici alim kaydi olmayan kalemler. Etiket
     * "Kalem · Talep eden · Departman · Durum".
     *
     * @return array<int, string>
     */
    public function requestOptions(string $search = '', ?int $only = null): array
    {
        $query = PurchaseNeed::query()
            ->notArchived()
            ->with(['requestedBy', 'orgUnit', 'catalogItem'])
            ->whereNotNull('catalog_item_id')
            ->where('status', '!=', PurchaseNeedStatus::Cancelled->value)
            ->latest('id')
            ->limit(50);

        if ($only !== null) {
            $query->whereKey($only);
        } else {
            $query->whereDoesntHave('companyPurchase')
                ->where(fn (Builder $kind): Builder => $kind
                    ->where('usage_area', UsageArea::Office->value)
                    ->orWhere('source', PurchaseNeedSource::OfficeRequest->value));
        }

        if (trim($search) !== '') {
            $query->where('title', 'like', '%'.trim($search).'%');
        }

        $options = [];

        foreach ($query->get() as $need) {
            /** @var PurchaseNeed $need */
            $options[(int) $need->getKey()] = implode(' · ', array_values(array_filter([
                (string) $need->getAttribute('title'),
                $need->requestedBy?->getAttribute('full_name'),
                $need->orgUnit?->getAttribute('name'),
                $need->statusEnum()->getLabel(),
            ])));
        }

        return $options;
    }

    /**
     * Talepten doldurulacak sirket ici alim alanlari (D-190: "direk talepten
     * gerekli alanlar dolu sekilde gelip isi hizlandirabilir"): katalog kalemi,
     * aciklama, departman, talep eden, miktar, onerilen teklifin tedarikcisi,
     * birim fiyati ve para birimi (yoksa tahmini maliyet), alim tarihi
     * (tamamlandiysa o gun), kalemin yenileme donemi.
     *
     * @return array<string, mixed>
     */
    public function prefillFromRequest(int $needId): array
    {
        /** @var PurchaseNeed|null $need */
        $need = PurchaseNeed::query()->with(['recommendedQuote', 'catalogItem'])->find($needId);

        if (! $need instanceof PurchaseNeed) {
            return [];
        }

        $quote = $need->recommendedQuote;
        $text = array_values(array_filter([
            (string) $need->getAttribute('title'),
            $need->getAttribute('brand_model'),
        ]));

        return [
            'catalog_item_id' => $need->getAttribute('catalog_item_id'),
            'description' => mb_substr(implode(' · ', $text), 0, 300),
            'org_unit_id' => $need->getAttribute('org_unit_id'),
            'personnel_id' => $need->getAttribute('requested_by_personnel_id'),
            'supplier_party_id' => $quote?->getAttribute('supplier_party_id'),
            'supplier_name' => $quote?->getAttribute('supplier_name'),
            'quantity' => (float) $need->getAttribute('quantity'),
            'unit_price' => $quote instanceof PurchaseQuote ? (float) $quote->getAttribute('unit_price') : ($need->getAttribute('estimated_unit_cost') !== null ? (float) $need->getAttribute('estimated_unit_cost') : null),
            'currency_code' => $quote?->getAttribute('currency_code') ?? $need->getAttribute('estimated_currency_code') ?? 'TRY',
            'purchased_on' => ($need->getAttribute('completed_at')?->timezone(DisplayTime::zone()) ?? Carbon::now(DisplayTime::zone()))->toDateString(),
            'renewal_months' => $need->catalogItem?->getAttribute('renewal_months'),
        ];
    }

    /**
     * Kalemli sirket ici alimin baglanacagi satin alma talepleri (D-192, B53):
     * kullanim yeri Sirket ici, iptal edilmemis, arsivde olmayan talepler.
     * Etiket "SAT-2026-0003 · Talep eden · Departman · Durum".
     *
     * @return array<int, string>
     */
    public function officeRequestOptions(string $search = '', ?int $only = null): array
    {
        if (! SchemaReadiness::hasBatch('B53')) {
            return [];
        }

        $query = PurchaseRequest::query()
            ->with(['requestedBy', 'orgUnit'])
            ->latest('id')
            ->limit(50);

        if ($only !== null) {
            $query->whereKey($only);
        } else {
            $query->notArchived()
                ->where('usage_area', UsageArea::Office->value)
                ->where('status', '!=', PurchaseRequestStatus::Cancelled->value);
        }

        if (trim($search) !== '') {
            $like = '%'.trim($search).'%';
            $query->where(fn (Builder $match): Builder => $match
                ->where('request_no', 'like', $like)
                ->orWhereHas('needs', fn (Builder $need): Builder => $need->where('title', 'like', $like)));
        }

        $options = [];

        foreach ($query->get() as $request) {
            /** @var PurchaseRequest $request */
            $options[(int) $request->getKey()] = implode(' · ', array_values(array_filter([
                (string) $request->getAttribute('request_no'),
                $request->requestedBy?->getAttribute('full_name'),
                $request->orgUnit?->getAttribute('name'),
                $request->statusEnum()->getLabel(),
            ])));
        }

        return $options;
    }

    /**
     * Talebin sirket ici alima girebilecek kalemleri (iptal edilmemis, alim
     * kaydi olmayan): kimlik => ad (miktar birim).
     *
     * @return array<int, string>
     */
    public function requestNeedOptions(mixed $requestId, ?int $including = null): array
    {
        if (blank($requestId) || ! SchemaReadiness::hasBatch('B53')) {
            return [];
        }

        $options = [];

        foreach ($this->requestNeeds((int) $requestId, $including) as $need) {
            $options[(int) $need->getKey()] = Str::limit((string) $need->getAttribute('title'), 100)
                .' ('.trim((Money::number($need->getAttribute('quantity'), 4) ?? '').' '.(string) app(UnitResolver::class)->display($need)).')';
        }

        return $options;
    }

    /**
     * Talep secilince formu dolduranlar (D-192): baslikta birim ve kisi,
     * kalemlerde talebin her kalemi (katalog kalemi, aciklama, miktar, tahmini
     * birim fiyat, katalog kaleminin yenileme donemi).
     *
     * @param  list<int>  $onlyNeeds  Bos degilse yalniz bu kalemler (toplu kalem ekle).
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>}
     */
    public function requestPrefill(int $requestId, array $onlyNeeds = []): array
    {
        $request = PurchaseRequest::query()->find($requestId);

        if (! $request instanceof PurchaseRequest) {
            return ['header' => [], 'lines' => []];
        }

        $lines = [];

        foreach ($this->requestNeeds($requestId) as $need) {
            if ($onlyNeeds !== [] && ! in_array((int) $need->getKey(), $onlyNeeds, true)) {
                continue;
            }

            $lines[] = [
                'catalog_item_id' => $need->getAttribute('catalog_item_id') !== null ? (int) $need->getAttribute('catalog_item_id') : null,
                'purchase_need_id' => (int) $need->getKey(),
                'description' => mb_substr(implode(' · ', array_values(array_filter([(string) $need->getAttribute('title'), $need->getAttribute('brand_model')]))), 0, 300),
                'quantity' => (float) $need->getAttribute('quantity'),
                'unit_price' => $need->getAttribute('estimated_unit_cost') !== null ? (float) $need->getAttribute('estimated_unit_cost') : null,
                'renewal_months' => $need->catalogItem?->getAttribute('renewal_months'),
            ];
        }

        return [
            'header' => [
                'org_unit_id' => $request->getAttribute('org_unit_id'),
                'personnel_id' => $request->getAttribute('requested_by_personnel_id'),
            ],
            'lines' => $lines,
        ];
    }

    /** Liste: kalemli alimin talep ve siparis baglantilari (B53). */
    public function withRequestLinks(Builder $query): Builder
    {
        return SchemaReadiness::hasBatch('B53') ? $query->with(['purchaseRequest', 'purchaseOrder']) : $query;
    }

    /**
     * @return iterable<PurchaseNeed>
     */
    private function requestNeeds(int $requestId, ?int $including = null): iterable
    {
        return PurchaseNeed::query()
            ->with('catalogItem')
            ->where('purchase_request_id', $requestId)
            ->where('status', '!=', PurchaseNeedStatus::Cancelled->value)
            ->where(fn (Builder $open): Builder => $including !== null
                ? $open->whereDoesntHave('companyPurchase')->orWhereKey($including)
                : $open->whereDoesntHave('companyPurchase'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** Yaklasan yenileme sayisi (pencere icinde). */
    public function upcomingCount(): int
    {
        return $this->tabScope(CompanyPurchase::query()->notArchived(), self::TAB_UPCOMING)->count();
    }

    private function today(): string
    {
        return Carbon::now(DisplayTime::zone())->toDateString();
    }
}
