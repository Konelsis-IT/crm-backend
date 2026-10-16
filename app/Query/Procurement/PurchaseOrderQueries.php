<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseOrder;
use App\Policies\PurchaseNeedPolicy;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Siparisler ekranlarinin okumalari (D-192, B53): kisinin gorebildigi
 * siparisler (satin alma ve ust yonetim hepsini, talep eden kendi kaleminin
 * siparisini), lojistik sekmeleri (Geciken dahil), arsiv suzgeci ve sayfa
 * iliskileri. Ekran siniflari sorgu yazmaz.
 */
final class PurchaseOrderQueries
{
    public const TAB_ALL = 'all';

    public const TAB_DRAFT = 'draft';

    public const TAB_AWAITING_APPROVAL = 'awaiting_approval';

    public const TAB_APPROVED = 'approved';

    public const TAB_ORDERED = 'ordered';

    public const TAB_IN_TRANSIT = 'in_transit';

    public const TAB_DELIVERED = 'delivered';

    public const TAB_COMPLETED = 'completed';

    public const TAB_LATE = 'late';

    public const TAB_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const TABS = [
        self::TAB_ALL, self::TAB_DRAFT, self::TAB_AWAITING_APPROVAL, self::TAB_APPROVED, self::TAB_ORDERED,
        self::TAB_IN_TRANSIT, self::TAB_DELIVERED, self::TAB_COMPLETED, self::TAB_LATE, self::TAB_CANCELLED,
    ];

    /** Kisinin gorebildigi siparisler (PurchaseOrderPolicy::view ile ayni kural). */
    public function visibleTo(Builder $query, ?Personnel $personnel): Builder
    {
        if (! $personnel instanceof Personnel) {
            return $query->whereKey([]);
        }

        $policy = app(PurchaseNeedPolicy::class);

        if ($policy->seesAll($personnel) || $policy->managesPurchasing($personnel)) {
            return $query;
        }

        // Talep eden kendi kaleminin siparisini; proje muduru projesinin siparisini gorur (D-196).
        $managesProject = static fn (Builder $project): Builder => $project->where('project_manager_employee_id', $personnel->getKey());

        return $query->where(fn (Builder $visible): Builder => $visible
            ->whereHas('project', $managesProject)
            ->orWhereHas('lines.need', fn (Builder $need): Builder => $need
                ->where('requested_by_personnel_id', $personnel->getKey())
                ->orWhereHas('project', $managesProject)));
    }

    /** Liste: tedarikci, teklif, proje ve kalem sayisi. */
    public function withListRelations(Builder $query): Builder
    {
        return $query
            ->with(['supplier', 'quotation', 'project'])
            ->withCount('lines as line_count');
    }

    /** Siparis sayfasi: kalemler (talep / proje / katalog), teklif, onay, kisiler. */
    public function loadDetail(PurchaseOrder $order): PurchaseOrder
    {
        $order->loadMissing([
            'supplier',
            'quotation',
            'project',
            'approvalRequest',
            'orderedBy',
            'completedBy',
            'createdBy',
            'lines.need.project',
            'lines.need.purchaseRequest',
            'lines.catalogItem.category.parent',
        ]);

        // Kalem satirlari para birimini siparisin kendisinden okur (satir basina sorgu yok).
        foreach ($order->lines as $line) {
            $line->setRelation('order', $order);
        }

        return $order;
    }

    public function tabScope(Builder $query, string $tab): Builder
    {
        $status = $query->qualifyColumn('status');

        return match ($tab) {
            self::TAB_DRAFT => $query->where($status, PurchaseOrderStatus::Draft->value),
            self::TAB_AWAITING_APPROVAL => $query->where($status, PurchaseOrderStatus::AwaitingApproval->value),
            self::TAB_APPROVED => $query->where($status, PurchaseOrderStatus::Approved->value),
            self::TAB_ORDERED => $query->where($status, PurchaseOrderStatus::Ordered->value),
            self::TAB_IN_TRANSIT => $query->where($status, PurchaseOrderStatus::InTransit->value),
            self::TAB_DELIVERED => $query->where($status, PurchaseOrderStatus::Delivered->value),
            self::TAB_COMPLETED => $query->where($status, PurchaseOrderStatus::Completed->value),
            self::TAB_CANCELLED => $query->where($status, PurchaseOrderStatus::Cancelled->value),
            self::TAB_LATE => $this->lateScope($query),
            default => $query,
        };
    }

    /**
     * Geciken: tedarikciye iletilmis / yolda ve beklenen teslim (yoksa teslim
     * doneminin sonu) bugunden once (PurchaseOrder::isLate ile ayni kural).
     */
    public function lateScope(Builder $query): Builder
    {
        $today = Carbon::now(DisplayTime::zone())->toDateString();
        $expected = $query->qualifyColumn('expected_delivery_on');
        $until = $query->qualifyColumn('delivery_until');

        return $query
            ->whereIn($query->qualifyColumn('status'), [PurchaseOrderStatus::Ordered->value, PurchaseOrderStatus::InTransit->value])
            ->where(fn (Builder $late): Builder => $late
                ->whereDate($expected, '<', $today)
                ->orWhere(fn (Builder $period): Builder => $period->whereNull($expected)->whereDate($until, '<', $today)));
    }

    /**
     * Sekme sayilari (kisinin gorebildigi, arsivde olmayan siparisler).
     *
     * @return array<string, int>
     */
    public function tabCounts(?Personnel $personnel): array
    {
        if (! SchemaReadiness::hasBatch('B53')) {
            return [];
        }

        $counts = [];

        foreach (self::TABS as $tab) {
            $counts[$tab] = $this->tabScope($this->visibleTo(PurchaseOrder::query()->notArchived(), $personnel), $tab)->count();
        }

        return $counts;
    }

    public function archiveScope(Builder $query, string $mode): Builder
    {
        if (! SchemaReadiness::hasBatch('B53')) {
            return $query;
        }

        $column = $query->qualifyColumn('archived_at');

        return match ($mode) {
            'archived' => $query->whereNotNull($column),
            'all' => $query,
            default => $query->whereNull($column),
        };
    }
}
