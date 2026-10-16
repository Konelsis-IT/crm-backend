<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Procurement\PurchaseNeedSource;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\UsageArea;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseNeed;
use App\Policies\PurchaseNeedPolicy;
use App\Query\Personnel\OrganizationQueries;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Siparis kalemleri okumalari (B52, D-189). Liste sekmeleri kullanicinin
 * istedigi gibidir: "Tumu / Teklif grubunun gonderdigi kalemler / Projenin
 * onay verdigi kalemler / Termin suresi gecmis / Acil" (+ Sirket ici,
 * Siparis verildi, Tamamlanan). Gorunurluk: satin alma, ust yonetim ve tam
 * yetkili hepsini; digerleri kendi ve biriminin taleplerini gorur.
 */
final class PurchaseNeedQueries
{
    public const TAB_ALL = 'all';

    public const TAB_FROM_PROPOSALS = 'from_proposals';

    public const TAB_PROJECT_APPROVED = 'project_approved';

    public const TAB_OVERDUE = 'overdue';

    public const TAB_URGENT = 'urgent';

    /** Satin alma talepleri (sat-03, D-190): departmanlarin formla actigi talepler. */
    public const TAB_REQUESTS = 'requests';

    /** Kullanim yeri Sirket ici (D-190; tur degil). */
    public const TAB_OFFICE = 'office';

    /** Kullanim yeri Saha (D-190). */
    public const TAB_FIELD = 'field';

    public const TAB_ORDERED = 'ordered';

    public const TAB_COMPLETED = 'completed';

    /** @var list<string> */
    public const TABS = [
        self::TAB_ALL, self::TAB_FROM_PROPOSALS, self::TAB_PROJECT_APPROVED, self::TAB_REQUESTS, self::TAB_OVERDUE,
        self::TAB_URGENT, self::TAB_OFFICE, self::TAB_FIELD, self::TAB_ORDERED, self::TAB_COMPLETED,
    ];

    /** Kisinin gorebildigi kalemler. */
    public function visibleTo(Builder $query, ?Personnel $personnel): Builder
    {
        if (! $personnel instanceof Personnel) {
            return $query->whereRaw('1 = 0');
        }

        if (app(PurchaseNeedPolicy::class)->seesAll($personnel)) {
            return $query;
        }

        // D-218: kisinin calistigi butun departmanlar (ek gorevler dahil).
        $unitIds = app(OrganizationQueries::class)->unitIdsOf((int) $personnel->getKey());

        return $query->where(function (Builder $own) use ($personnel, $unitIds): void {
            $own->where($own->qualifyColumn('requested_by_personnel_id'), $personnel->getKey());

            if ($unitIds !== []) {
                $own->orWhereIn($own->qualifyColumn('org_unit_id'), $unitIds);
            }

            $own->orWhereHas('project', fn (Builder $project): Builder => $project->where('project_manager_employee_id', $personnel->getKey()));
        });
    }

    public function tabScope(Builder $query, string $tab): Builder
    {
        $pending = array_map(static fn (PurchaseNeedStatus $status): string => $status->value, PurchaseNeedStatus::pendingOrder());

        return match ($tab) {
            self::TAB_FROM_PROPOSALS => $query
                ->where('source', PurchaseNeedSource::ProposalCost->value)
                ->where('status', PurchaseNeedStatus::AwaitingProject->value),
            self::TAB_PROJECT_APPROVED => $query
                ->where('source', PurchaseNeedSource::ProposalCost->value)
                ->whereNotNull('project_approved_at')
                ->whereIn('status', $pending),
            self::TAB_OVERDUE => $query
                ->whereNotNull('needed_by')
                ->whereDate('needed_by', '<', $this->today())
                ->whereIn('status', [...$pending, PurchaseNeedStatus::AwaitingProject->value]),
            self::TAB_URGENT => $query
                ->where('is_urgent', true)
                ->whereNotIn('status', [PurchaseNeedStatus::Completed->value, PurchaseNeedStatus::Cancelled->value]),
            self::TAB_REQUESTS => $query->where('source', PurchaseNeedSource::OfficeRequest->value),
            self::TAB_OFFICE => SchemaReadiness::hasBatch('B52A')
                ? $query->where('usage_area', UsageArea::Office->value)
                : $query->where('source', PurchaseNeedSource::OfficeRequest->value),
            self::TAB_FIELD => SchemaReadiness::hasBatch('B52A')
                ? $query->where('usage_area', UsageArea::Field->value)
                : $query->where('source', PurchaseNeedSource::ProposalCost->value),
            self::TAB_ORDERED => $query->where('status', PurchaseNeedStatus::Ordered->value),
            self::TAB_COMPLETED => $query->where('status', PurchaseNeedStatus::Completed->value),
            default => $query,
        };
    }

    /** Liste siralamasi (15 Ekim 2026): acil kalemler en ustte, sonra en yeni. */
    public function urgentFirst(Builder $query): Builder
    {
        return $query
            ->orderByDesc($query->qualifyColumn('is_urgent'))
            ->orderByDesc($query->qualifyColumn('id'));
    }

    /** "Arsiv" suzgeci: active (varsayilan) / archived / all. */
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
     * Sekme rozetleri.
     *
     * @return array<string, int>
     */
    public function tabCounts(?Personnel $personnel): array
    {
        $counts = [];

        foreach (self::TABS as $tab) {
            $query = $this->visibleTo(PurchaseNeed::query()->notArchived(), $personnel);
            $counts[$tab] = $this->tabScope($query, $tab)->count();
        }

        return $counts;
    }

    /**
     * Yonetim panosu: satin almanin onerdigi teklifler (onerilen teklifi olan,
     * siparisi verilmemis kalemler).
     */
    public function recommendations(): Builder
    {
        return PurchaseNeed::query()
            ->notArchived()
            ->whereNotNull('recommended_quote_id')
            ->whereIn('status', [
                PurchaseNeedStatus::Recommended->value,
                PurchaseNeedStatus::AwaitingApproval->value,
                PurchaseNeedStatus::Approved->value,
            ])
            ->with(['recommendedQuote.supplier', 'proposal.businessCase', 'project', 'catalogItem'])
            ->orderByDesc('is_urgent')
            ->orderByRaw('needed_by IS NULL')
            ->orderBy('needed_by');
    }

    /**
     * Katalog kaleminin satin alinacak kalemleri (kalem sayfasindaki sekme).
     * Iliski doner: RelationManager duz sorgu (Builder) kabul etmez.
     */
    public function forCatalogItem(CatalogItem $item): HasMany
    {
        return $item->needs()
            ->with(['quotes.supplier', 'recommendedQuote', 'proposal', 'project'])
            ->latest('id');
    }

    private function today(): string
    {
        return Carbon::now(DisplayTime::zone())->toDateString();
    }
}
