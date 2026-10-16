<?php

declare(strict_types=1);

namespace App\Query\Project;

use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Project\ExpectationKind;
use App\Enums\Project\SupplyItemKind;
use App\Enums\Project\SupplyItemStatus;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\CatalogCategory;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Project\Project;
use App\Models\Project\ProjectSupplyItem;
use App\Policies\PurchaseNeedPolicy;
use App\Query\Procurement\PurchaseNeedQueries;
use App\Query\Procurement\PurchaseRequestQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Proje sayfasinin satin alma okumalari (D-196; anahtar
 * App\Support\Projects\ProjectPurchasing): Satin alma / Lojistik / Yazilim
 * tablolari, adim hazirligindaki tedarik beklentilerinin satin alma sayimi
 * ve eski tedarik kalemlerinin (project_supply_items) varligi.
 *
 * Gorunurluk satin alma ekranlariyla aynidir: kalemler
 * PurchaseNeedQueries::visibleTo (satin alma ve ust yonetim hepsini; talep
 * eden, ayni birim ve projenin proje muduru kendi kalemlerini). Siparisler:
 * satin alma ve ust yonetim hepsini, digerleri gorebildigi bir kalemi tasiyan
 * siparisi (projenin proje muduru projesinin siparislerinin lojistigini
 * gorur; siparis sayfasini acma yetkisi PurchaseOrderPolicy'de kalir, satir
 * yalniz yetkisi olana baglanti olur).
 */
final class ProjectPurchasingQueries
{
    public const LEGACY_SUPPLY = 'supply';

    public const LEGACY_LOGISTICS = 'logistics';

    public const LEGACY_SOFTWARE = 'software';

    /** Satin alma kayitlarini da sayan hazirlik beklentileri. */
    public const READINESS_KINDS = [
        ExpectationKind::SupplyItems,
        ExpectationKind::SupplyOrdered,
        ExpectationKind::SupplyDelivered,
        ExpectationKind::SoftwareItems,
    ];

    /**
     * Yazilim kalemi kurali (D-196). Satin almada "yazilim" diye bir tur yok
     * (D-190: tur Urun / Hizmet / Genel gider / Idari kadro; "Claude aboneligi
     * ayni zamanda bir hizmettir"), bu yuzden kural kalemin yerine bakar:
     *
     * 1. Kalemin Malzeme ve Hizmetler kategorisi ya da o kategorinin ust
     *    kategorisi adinda bu sozcuklerden biri geciyorsa ("Yazılım ve
     *    abonelikler", "SCADA" ve alti: Inverter izleme, RTU, haberlesme...),
     * 2. ya da kalemin kendi adinda geciyorsa ("SCADA yazılımı lisansı"),
     * 3. ya da kalem listede yoksa ("Listede yok" ile acilan talep) kalem
     *    adinda / istenen urun adinda geciyorsa.
     *
     * Eski proje sekmesinin adi "Yazılım / otomasyon kalemleri" oldugu icin
     * SCADA ve otomasyon da sayilir. Arama buyuk / kucuk harf duyarsizdir;
     * "yazilim" ASCII yazimi "YAZILIM" gibi buyuk harfli adlari da yakalar.
     *
     * @var list<string>
     */
    public const SOFTWARE_KEYWORDS = ['yazılım', 'yazilim', 'scada', 'otomasyon'];

    /** Siparisi verilmis (tedarikciye iletilmis ya da sonrasi) siparis asamalari. */
    private const ORDERED_STAGES = [
        PurchaseOrderStatus::Ordered,
        PurchaseOrderStatus::InTransit,
        PurchaseOrderStatus::Delivered,
        PurchaseOrderStatus::Completed,
    ];

    /** Teslim alinmis siparis asamalari. */
    private const DELIVERED_STAGES = [
        PurchaseOrderStatus::Delivered,
        PurchaseOrderStatus::Completed,
    ];

    /** @var list<int>|null */
    private ?array $softwareCategoryIds = null;

    /**
     * "Satin alma" ve "Yazilim" tablolari: arsivsiz, kisinin gorebildigi
     * kalemler; teklif ve siparis sutunlari icin bagli kayitlar yuklu.
     */
    public function needsTable(Builder $needs, ?Personnel $personnel): Builder
    {
        $needs->notArchived();

        /** @var Builder $query */
        $query = app(PurchaseRequestQueries::class)->withLineRelations(
            app(PurchaseNeedQueries::class)->visibleTo($needs, $personnel),
        );

        return $query;
    }

    /** Yazilim kalemleri (kural: SOFTWARE_KEYWORDS). */
    public function softwareScope(Builder $needs): Builder
    {
        $categoryIds = $this->softwareCategoryIds();

        return $needs->where(function (Builder $rule) use ($categoryIds): void {
            $rule->whereHas('catalogItem', function (Builder $item) use ($categoryIds): void {
                $item->where(function (Builder $match) use ($categoryIds): void {
                    if ($categoryIds !== []) {
                        $match->whereIn($match->qualifyColumn('catalog_category_id'), $categoryIds);
                    }

                    $this->orMatchesKeyword($match, 'name');
                });
            })->orWhere(function (Builder $free): void {
                $free->whereNull($free->qualifyColumn('catalog_item_id'))
                    ->where(function (Builder $text): void {
                        $this->orMatchesKeyword($text, 'title');
                        $this->orMatchesKeyword($text, 'requested_item_name');
                    });
            });
        });
    }

    /**
     * "Lojistik" tablosunun iliskisi: projeye yazilmis siparisler ve birden
     * fazla projenin kalemini tasiyan (project_id bos) siparislerden bu
     * projenin kalemi olanlar. RelationManager iliski ister (Builder degil),
     * bu yuzden HasMany kisitsiz kurulup kosul tek grupta eklenir; tablo salt
     * okunurdur (iliskiyle yazma yok).
     */
    public function ordersRelation(Project $project): HasMany
    {
        $projectId = (int) $project->getKey();

        /** @var HasMany $relation */
        $relation = Relation::noConstraints(static fn (): HasMany => $project->purchaseOrders());

        $relation->where(function (Builder $orders) use ($projectId): void {
            $orders->where($orders->qualifyColumn('project_id'), $projectId)
                ->orWhereHas('lines.need', fn (Builder $need): Builder => $need->where($need->qualifyColumn('project_id'), $projectId));
        });

        return $relation;
    }

    /** "Lojistik" tablosu: arsivsiz, kisinin gorebildigi siparisler; tedarikciyle. */
    public function ordersTable(Builder $orders, ?Personnel $personnel): Builder
    {
        $orders->notArchived()->with('supplier');

        if (! $personnel instanceof Personnel) {
            return $orders->whereKey([]);
        }

        $policy = app(PurchaseNeedPolicy::class);

        if ($policy->seesAll($personnel) || $policy->managesPurchasing($personnel)) {
            return $orders;
        }

        return $orders->whereHas('lines.need', fn (Builder $need): Builder => app(PurchaseNeedQueries::class)->visibleTo($need, $personnel));
    }

    /**
     * Projede eski tedarik kalemi var mi (bolume gore: hepsi / siparisi
     * verilmis / yazilim; LogisticsItemsRelationManager ve
     * SoftwareItemsRelationManager suzgecleriyle ayni kural).
     */
    public function hasLegacy(Project $project, string $scope): bool
    {
        $query = ProjectSupplyItem::query()->where('project_id', (int) $project->getKey());

        if ($scope === self::LEGACY_LOGISTICS) {
            $query->whereIn('status', array_map(
                static fn (SupplyItemStatus $status): string => $status->value,
                SupplyItemStatus::orderedStates(),
            ));
        } elseif ($scope === self::LEGACY_SOFTWARE) {
            $query->where('item_kind', SupplyItemKind::Software->value);
        }

        return $query->exists();
    }

    /**
     * Hazirlik beklentisinin satin alma sayimi (eski tedarik kalemleri
     * ProjectStepReadiness'te ayrica sayilir, toplanir). Arsivdeki ve iptal
     * edilen kalemler sayilmaz.
     *
     * - Tedarik kalemleri: projenin kalemleri.
     * - Siparis verilmis: kalem Siparis verildi / Tamamlandi ya da kalemin
     *   siparisi tedarikciye iletilmis (Siparis verildi, Yolda, Teslim alindi,
     *   Tamamlandi).
     * - Teslim edilmis: kalem Tamamlandi ya da siparis satirinin tamami
     *   teslim alinmis ya da siparisi Teslim alindi / Tamamlandi.
     * - Yazilim: yazilim kurali (SOFTWARE_KEYWORDS) tutan kalemler.
     */
    public function readinessCount(Project $project, ExpectationKind $kind): int
    {
        $needs = PurchaseNeed::query()
            ->where('project_id', (int) $project->getKey())
            ->notArchived()
            ->where('status', '!=', PurchaseNeedStatus::Cancelled->value);

        return match ($kind) {
            ExpectationKind::SupplyItems => $needs->count(),
            ExpectationKind::SupplyOrdered => $needs->where(function (Builder $ordered): void {
                $ordered->whereIn('status', [PurchaseNeedStatus::Ordered->value, PurchaseNeedStatus::Completed->value])
                    ->orWhereHas('orderLines.order', fn (Builder $order): Builder => $order->notArchived()->whereIn('status', self::values(self::ORDERED_STAGES)));
            })->count(),
            ExpectationKind::SupplyDelivered => $needs->where(function (Builder $delivered): void {
                $delivered->where('status', PurchaseNeedStatus::Completed->value)
                    ->orWhereHas('orderLines', function (Builder $line): void {
                        $line->whereHas('order', fn (Builder $order): Builder => $order->notArchived()->where('status', '!=', PurchaseOrderStatus::Cancelled->value))
                            ->where(function (Builder $received): void {
                                $received->whereColumn($received->qualifyColumn('received_quantity'), '>=', $received->qualifyColumn('quantity'))
                                    ->orWhereHas('order', fn (Builder $order): Builder => $order->whereIn('status', self::values(self::DELIVERED_STAGES)));
                            });
                    });
            })->count(),
            ExpectationKind::SoftwareItems => $this->softwareScope($needs)->count(),
            default => 0,
        };
    }

    /**
     * Adi yazilim sozcuklerinden birini tasiyan kategoriler ve alt
     * kategorileri (istek basina bir kez).
     *
     * @return list<int>
     */
    private function softwareCategoryIds(): array
    {
        if ($this->softwareCategoryIds !== null) {
            return $this->softwareCategoryIds;
        }

        $roots = CatalogCategory::query()
            ->where(fn (Builder $match) => $this->orMatchesKeyword($match, 'name'))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $children = $roots === [] ? [] : CatalogCategory::query()
            ->whereIn('parent_id', $roots)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $this->softwareCategoryIds = array_values(array_unique([...$roots, ...$children]));
    }

    /** Grup icinde: kolon sozcuklerden birini iceriyor (orWhere zinciri). */
    private function orMatchesKeyword(Builder $query, string $column): void
    {
        foreach (self::SOFTWARE_KEYWORDS as $keyword) {
            $query->orWhere($query->qualifyColumn($column), 'like', '%'.$keyword.'%');
        }
    }

    /**
     * @param  list<PurchaseOrderStatus>  $statuses
     * @return list<string>
     */
    private static function values(array $statuses): array
    {
        return array_map(static fn (PurchaseOrderStatus $status): string => $status->value, $statuses);
    }
}
