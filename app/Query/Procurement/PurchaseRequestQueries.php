<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\PurchaseQuotationStatus;
use App\Enums\Procurement\PurchaseRequestStatus;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Models\Procurement\PurchaseRequest;
use App\Policies\PurchaseNeedPolicy;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kalemli satin alma talepleri okumalari (D-192, B53). Gorunurluk satin alma
 * kalemleriyle aynidir (PurchaseNeedQueries): talep eden, ayni birim; satin
 * alma, ust yonetim ve tam yetkili hepsini gorur. Sekmeler: Tumu / Benim
 * taleplerim / Acik / Isleniyor / Tamamlandi / Iptal. B53 uygulanmadan once
 * butun okumalar bos doner.
 */
final class PurchaseRequestQueries
{
    public const TAB_ALL = 'all';

    public const TAB_MINE = 'mine';

    public const TAB_OPEN = 'open';

    public const TAB_IN_PROGRESS = 'in_progress';

    public const TAB_COMPLETED = 'completed';

    public const TAB_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const TABS = [self::TAB_ALL, self::TAB_MINE, self::TAB_OPEN, self::TAB_IN_PROGRESS, self::TAB_COMPLETED, self::TAB_CANCELLED];

    public static function ready(): bool
    {
        return SchemaReadiness::hasBatch('B53');
    }

    /** Kisinin gorebildigi talepler. */
    public function visibleTo(Builder $query, ?Personnel $personnel): Builder
    {
        if (! $personnel instanceof Personnel || ! self::ready()) {
            return $query->whereKey([]);
        }

        if (app(PurchaseNeedPolicy::class)->seesAll($personnel)) {
            return $query;
        }

        $unitId = $personnel->getAttribute('org_unit_id');

        return $query->where(function (Builder $own) use ($personnel, $unitId): void {
            $own->where($own->qualifyColumn('requested_by_personnel_id'), $personnel->getKey());

            if ($unitId !== null) {
                $own->orWhere($own->qualifyColumn('org_unit_id'), $unitId);
            }
        });
    }

    /** Liste icin bagli kayitlar ve kalem sayisi. */
    public function withListRelations(Builder $query): Builder
    {
        if (! self::ready()) {
            return $query;
        }

        // Formdan kaldirilip iptal edilen kalemler sayilmaz. Kalem adlari talebin
        // basligi icin (PurchaseRequest::displayTitle).
        return $query
            ->with(['requestedBy', 'orgUnit', 'project', 'needs:id,purchase_request_id,title,status,sort_order'])
            ->withCount(['needs as lines_count' => fn (Builder $needs): Builder => $needs->where('status', '!=', PurchaseNeedStatus::Cancelled->value)]);
    }

    /** Liste aramasi: SAT kodu, kalem adlari ya da gerekce (Turkce arama, D-214). */
    public function search(Builder $query, string $term): Builder
    {
        $like = '%'.trim($term).'%';

        return $query->where(fn (Builder $inner): Builder => $inner
            ->where($inner->qualifyColumn('request_no'), 'like', $like)
            ->orWhere($inner->qualifyColumn('justification'), 'like', $like)
            ->orWhereHas('needs', fn (Builder $needs): Builder => $needs->where('title', 'like', $like)));
    }

    public function tabScope(Builder $query, string $tab, ?Personnel $personnel): Builder
    {
        return match ($tab) {
            self::TAB_MINE => $query->where($query->qualifyColumn('requested_by_personnel_id'), $personnel?->getKey() ?? 0),
            self::TAB_OPEN => $query->where($query->qualifyColumn('status'), PurchaseRequestStatus::Open->value),
            self::TAB_IN_PROGRESS => $query->where($query->qualifyColumn('status'), PurchaseRequestStatus::InProgress->value),
            self::TAB_COMPLETED => $query->where($query->qualifyColumn('status'), PurchaseRequestStatus::Completed->value),
            self::TAB_CANCELLED => $query->where($query->qualifyColumn('status'), PurchaseRequestStatus::Cancelled->value),
            default => $query,
        };
    }

    /** "Arsiv" suzgeci: active (varsayilan) / archived / all (D-156). */
    public function archiveScope(Builder $query, string $mode): Builder
    {
        if (! self::ready()) {
            return $query;
        }

        return match ($mode) {
            'archived' => $query->whereNotNull($query->qualifyColumn('archived_at')),
            'all' => $query,
            default => $query->whereNull($query->qualifyColumn('archived_at')),
        };
    }

    /**
     * Sekme rozetleri (arsivliler haric).
     *
     * @return array<string, int>
     */
    public function tabCounts(?Personnel $personnel): array
    {
        if (! self::ready()) {
            return [];
        }

        $counts = [];

        foreach (self::TABS as $tab) {
            $counts[$tab] = $this->tabScope($this->visibleTo(PurchaseRequest::query()->notArchived(), $personnel), $tab, $personnel)->count();
        }

        return $counts;
    }

    /** Talep sayfasindaki kalem tablosunun sorgusu: teklif satirlari ve siparisle. */
    public function withLineRelations(HasMany | Builder $query): HasMany | Builder
    {
        return $query->with([
            'catalogItem.category.parent',
            'quotationLines.quotation.supplier',
            'orderLines.order',
        ]);
    }

    /**
     * Kalemin girdigi satin alma teklifleri (yeniden eskiye; ayni teklif bir kez).
     *
     * @return list<PurchaseQuotation>
     */
    public function quotationsOf(PurchaseNeed $need): array
    {
        if (! self::ready()) {
            return [];
        }

        $quotations = [];

        foreach ($need->quotationLines as $line) {
            /** @var PurchaseQuotationLine $line */
            $quotation = $line->quotation;

            if ($quotation instanceof PurchaseQuotation) {
                $quotations[(int) $quotation->getKey()] = $quotation;
            }
        }

        krsort($quotations);

        return array_values($quotations);
    }

    /** Kalemin siparisi (iptal edilmemis olan once). */
    public function orderOf(PurchaseNeed $need): ?PurchaseOrder
    {
        if (! self::ready()) {
            return null;
        }

        $found = null;

        foreach ($need->orderLines as $line) {
            /** @var PurchaseOrderLine $line */
            $order = $line->order;

            if (! $order instanceof PurchaseOrder) {
                continue;
            }

            if ($found === null || $found->statusEnum() === PurchaseOrderStatus::Cancelled) {
                $found = $order;
            }
        }

        return $found;
    }

    /**
     * Kalemin teklif satirlari (kalem sayfasindaki "Satin alma teklifleri"
     * bolumu): teklif, tedarikci ve siparis satiriyla.
     *
     * @return list<PurchaseQuotationLine>
     */
    public function quotationLinesOf(PurchaseNeed $need): array
    {
        if (! self::ready()) {
            return [];
        }

        return $need->quotationLines()
            ->with(['quotation.supplier', 'orderLine.order'])
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    /** "Mevcut teklife ekle" penceresinde secilen teklif (arsivsiz). */
    public function quotation(mixed $id): ?PurchaseQuotation
    {
        if (! self::ready() || blank($id)) {
            return null;
        }

        $quotation = PurchaseQuotation::query()->notArchived()->find((int) $id);

        return $quotation instanceof PurchaseQuotation ? $quotation : null;
    }

    /**
     * "Mevcut teklife ekle" icin degisebilen satin alma teklifleri: kod ustte,
     * tedarikci ve durum altta (iki satirli secim).
     *
     * @return array<int, array{0: string, 1: string|null}>
     */
    public function editableQuotationRows(): array
    {
        if (! self::ready()) {
            return [];
        }

        $rows = [];

        $quotations = PurchaseQuotation::query()
            ->notArchived()
            ->whereIn('status', array_map(
                static fn (PurchaseQuotationStatus $status): string => $status->value,
                array_filter(PurchaseQuotationStatus::cases(), static fn (PurchaseQuotationStatus $status): bool => $status->isEditable()),
            ))
            ->with('supplier')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        foreach ($quotations as $quotation) {
            /** @var PurchaseQuotation $quotation */
            $supplier = $quotation->supplierLabel();
            $rows[(int) $quotation->getKey()] = [
                (string) $quotation->getAttribute('quotation_no'),
                trim(($supplier !== '' ? $supplier.' · ' : '').(string) $quotation->statusEnum()->getLabel()),
            ];
        }

        return $rows;
    }
}
