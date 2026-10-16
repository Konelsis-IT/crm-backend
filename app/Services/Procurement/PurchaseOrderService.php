<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Approval\ApprovalRequestStatus;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\PurchaseQuotationStatus;
use App\Enums\Procurement\UsageArea;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Procurement\PurchaseApprovalRequiredException;
use App\Exceptions\Procurement\PurchaseOrderClosedException;
use App\Exceptions\Procurement\PurchaseOrderExistsException;
use App\Exceptions\Procurement\PurchaseQuotationEmptyException;
use App\Exceptions\Procurement\PurchaseQuotationNotSelectedException;
use App\Exceptions\Procurement\PurchaseQuoteRateRequiredException;
use App\Exceptions\Procurement\QuotationLineCatalogRequiredException;
use App\Models\Approval\ApprovalRequest;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Services\AbstractService;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Approval\Subjects\PurchaseOrderSubject;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Numbering\YearlyCodeAllocator;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\DisplayTime;
use App\Support\Procurement\UnitResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Siparisler (D-192, B53; kullanici 12 Ekim 2026: "Siparisler ise ilgili
 * teklif onaylandiginda olusan surec icin kullanilan bir yapidir. Tekliften
 * gelir. Secili teklif siparise donusur. Ayni zamanda logistic sureci de
 * olmasi lazim. Teslim edilecegi donem ... teslim edilecegi adres").
 *
 * - `createFromQuotation`: secili teklif bir kez siparise donusur (kod
 *   SIP-YYYY-NNNN); opsiyon ve iptal disindaki kalemler kopyalanir, her
 *   kalemin Malzeme ve Hizmetler kalemi olmalidir. Teklif Siparis
 *   verildi olur.
 * - Onay (PurchaseOrderApprovals): 4.000 TL'ye kadar Satin alma muduru, ustu
 *   Yonetim kurulu baskani; butun kalemler "Onay gerekmez" ise onaysiz.
 *   Onay olmadan siparis verilemez ve tamamlanamaz (ekran merci listesini
 *   uyari penceresinde gosterir).
 * - Lojistik: Siparis verildi -> Yolda (tasiyici, takip no) -> Teslim alindi
 *   (teslim alinan miktarlar) -> Tamamlandi. Kalemler (Satin alinacaklar)
 *   siparisle birlikte ilerler; tamamlanan Sirket ici kalem Sirket ici
 *   alimlar'a yazilir.
 * - Iptal: teklif de iptal olur, kalemler yeniden teklif toplanabilir. Silme
 *   yok, arsiv var (D-156).
 */
final class PurchaseOrderService extends AbstractService
{
    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    /** @var list<string> Siparis acilirken ve sonra degisebilen teslim / lojistik alanlari. */
    private const LOGISTICS = [
        'delivery_address', 'delivery_contact', 'delivery_from', 'delivery_until', 'expected_delivery_on', 'carrier',
        'tracking_no', 'payment_terms', 'note', 'project_id',
    ];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly YearlyCodeAllocator $codes,
        private readonly PurchaseNeedService $needs,
        private readonly PurchaseQuotationService $quotations,
        private readonly PurchaseOrderApprovals $approvals,
        private readonly ApprovalRequestService $approvalRequests,
        private readonly CompanyPurchaseService $companyPurchases,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /** Siparis yalniz tekliften acilir. */
    public function create(array $data): Model
    {
        throw InvalidTransitionException::make();
    }

    /**
     * Secili tekliften siparis.
     *
     * @param  array<string, mixed>  $data  Teslim / lojistik alanlari.
     */
    public function createFromQuotation(PurchaseQuotation $quotation, array $data = []): PurchaseOrder
    {
        return $this->transactions->run(function () use ($quotation, $data): PurchaseOrder {
            /** @var PurchaseQuotation $locked */
            $locked = PurchaseQuotation::query()->lockForUpdate()->findOrFail($quotation->getKey());

            if ($locked->order()->exists()) {
                throw PurchaseOrderExistsException::make();
            }

            if ($locked->isArchived() || $locked->statusEnum() !== PurchaseQuotationStatus::Selected) {
                throw PurchaseQuotationNotSelectedException::make();
            }

            if ($locked->totalInTry() === null) {
                throw PurchaseQuoteRateRequiredException::make();
            }

            $lines = $locked->lines()->with('need')->get()->filter(static fn (PurchaseQuotationLine $line): bool => $line->counts())->values();

            if ($lines->isEmpty()) {
                throw PurchaseQuotationEmptyException::make();
            }

            foreach ($lines as $line) {
                if (self::itemIdOf($line) === null) {
                    throw QuotationLineCatalogRequiredException::make(['line' => Str::limit((string) $line->getAttribute('description'), 80)]);
                }
            }

            $projects = $lines->map(static fn (PurchaseQuotationLine $line): ?int => $line->need?->getAttribute('project_id') !== null ? (int) $line->need->getAttribute('project_id') : null)
                ->filter()->unique()->values();
            $logistics = array_intersect_key($data, array_flip(self::LOGISTICS));

            /** @var PurchaseOrder $order */
            $order = parent::create([
                ...$logistics,
                'order_no' => $this->codes->next('SIP')['formatted_code'],
                'purchase_quotation_id' => $locked->getKey(),
                'supplier_party_id' => $locked->getAttribute('supplier_party_id'),
                'supplier_name' => $locked->getAttribute('supplier_name'),
                'project_id' => $logistics['project_id'] ?? ($projects->count() === 1 ? $projects->first() : null),
                'currency_code' => $locked->getAttribute('currency_code'),
                'try_rate' => $locked->getAttribute('try_rate'),
                'subtotal' => $locked->getAttribute('subtotal'),
                'discount_total' => $locked->getAttribute('discount_total'),
                'total_amount' => $locked->getAttribute('total_amount'),
                'payment_terms' => $logistics['payment_terms'] ?? $locked->getAttribute('payment_terms'),
                'expected_delivery_on' => $logistics['expected_delivery_on'] ?? $locked->getAttribute('delivery_on')?->toDateString(),
                'status' => PurchaseOrderStatus::Draft->value,
            ]);

            foreach ($lines as $index => $line) {
                PurchaseOrderLine::query()->create([
                    'purchase_order_id' => $order->getKey(),
                    'purchase_quotation_line_id' => $line->getKey(),
                    'purchase_need_id' => $line->getAttribute('purchase_need_id'),
                    'catalog_item_id' => self::itemIdOf($line),
                    'description' => $line->getAttribute('description'),
                    'brand_model' => $line->getAttribute('brand_model'),
                    'quantity' => $line->getAttribute('quantity'),
                    'unit' => $line->getAttribute('unit'),
                    // D-194 (B54): teklif satirinin sabit birimi siparise gecer.
                    ...(UnitResolver::enabled() ? ['uom_id' => $line->getAttribute('uom_id')] : []),
                    'unit_price' => $line->getAttribute('unit_price'),
                    'discount_rate' => $line->getAttribute('discount_rate'),
                    'line_total' => $line->getAttribute('line_total'),
                    'received_quantity' => 0,
                    'sort_order' => $index,
                ]);
            }

            $this->quotations->markOrdered($locked);

            return $order->refresh();
        });
    }

    /** Teslim ve lojistik bilgileri (kapanmamis sipariste). */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): PurchaseOrder {
            /** @var PurchaseOrder $order */
            $order = $this->lockForUpdate($record);
            $this->assertOpen($order);

            /** @var PurchaseOrder $order */
            $order = parent::update($order, array_intersect_key($data, array_flip([...self::LOGISTICS, 'row_version'])));

            return $order;
        });
    }

    public function approvalState(PurchaseOrder $order): PurchaseApprovalState
    {
        return $this->approvals->state($order);
    }

    /**
     * Onaya gonderir. Onay gerekmeyen sipariste bir sey yapmaz. Tek adimli
     * politikada onayci kisinin kendisiyse (yetkisi dahilinde) talep acilmadan
     * onaylanir.
     */
    public function requestApproval(PurchaseOrder $order, ?string $note = null): PurchaseOrder
    {
        return $this->transactions->run(function () use ($order, $note): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = $this->lockForUpdate($order);
            $this->assertOpen($locked);

            if ($locked->statusEnum() !== PurchaseOrderStatus::Draft) {
                return $locked;
            }

            $state = $this->approvals->state($locked);

            if (! $state->required || $state->satisfied) {
                return $locked;
            }

            if ($state->missing === PurchaseApprovalState::MISSING_RATE) {
                throw PurchaseQuoteRateRequiredException::make();
            }

            if ($state->selfApprovable) {
                $this->transition($locked, PurchaseOrderStatus::Approved, 'self_approved', [], ['tutar_tl' => $state->amountTry]);
                $this->moveNeeds($locked, PurchaseNeedStatus::Approved, 'approval_approved');

                return $locked;
            }

            $request = $this->approvalRequests->request(PurchaseOrderSubject::TYPE, (int) $locked->getKey(), null, null, $note);
            $this->transition($locked, PurchaseOrderStatus::AwaitingApproval, 'approval_requested', [
                'approval_request_id' => $request->getKey(),
            ], ['tutar_tl' => $state->amountTry, 'merci' => $state->approversText()]);
            $this->moveNeeds($locked, PurchaseNeedStatus::AwaitingApproval, 'approval_requested');

            return $locked;
        });
    }

    /** Onay motorunun sonucu (PurchaseOrderSubject). */
    public function applyApproval(int $orderId, string $outcome, ?string $comment): void
    {
        $this->transactions->run(function () use ($orderId, $outcome, $comment): void {
            /** @var PurchaseOrder|null $order */
            $order = PurchaseOrder::query()->lockForUpdate()->find($orderId);

            if ($order === null || $order->statusEnum() !== PurchaseOrderStatus::AwaitingApproval) {
                return;
            }

            $approved = $outcome === 'approved';
            $this->transition($order, $approved ? PurchaseOrderStatus::Approved : PurchaseOrderStatus::Draft, 'approval_'.$outcome, [], $comment !== null ? ['aciklama' => $comment] : []);
            $this->moveNeeds($order, $approved ? PurchaseNeedStatus::Approved : PurchaseNeedStatus::Recommended, 'approval_'.$outcome);
        });
    }

    /**
     * Siparis tedarikciye iletildi. Onay gereken sipariste onay saglanmadan verilemez.
     *
     * @param  array{expected_delivery_on?: string|null, note?: string|null}  $data
     */
    public function placeOrder(PurchaseOrder $order, array $data = []): PurchaseOrder
    {
        return $this->transactions->run(function () use ($order, $data): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = $this->lockForUpdate($order);
            $this->assertOpen($locked);

            if (! in_array($locked->statusEnum(), [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved], true)) {
                throw InvalidTransitionException::make();
            }

            $this->assertApproved($locked);
            $expected = filled($data['expected_delivery_on'] ?? null) ? (string) $data['expected_delivery_on'] : $locked->getAttribute('expected_delivery_on')?->toDateString();

            $this->transition($locked, PurchaseOrderStatus::Ordered, 'ordered', [
                'ordered_by_personnel_id' => $this->actor->personnelId(),
                'ordered_at' => Carbon::now('UTC'),
                'expected_delivery_on' => $expected,
                'note' => self::appendNote($locked->getAttribute('note'), $data['note'] ?? null),
            ]);
            $this->moveNeeds($locked, PurchaseNeedStatus::Ordered, 'ordered', [
                'ordered_by_personnel_id' => $this->actor->personnelId(),
                'ordered_at' => Carbon::now('UTC'),
                'expected_delivery_on' => $expected,
            ]);

            return $locked;
        });
    }

    /**
     * Yola cikti (kargo / nakliye).
     *
     * @param  array{carrier?: string|null, tracking_no?: string|null, expected_delivery_on?: string|null}  $data
     */
    public function markShipped(PurchaseOrder $order, array $data = []): PurchaseOrder
    {
        return $this->transactions->run(function () use ($order, $data): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = $this->lockForUpdate($order);
            $this->assertOpen($locked);

            if ($locked->statusEnum() !== PurchaseOrderStatus::Ordered) {
                throw InvalidTransitionException::make();
            }

            return $this->transition($locked, PurchaseOrderStatus::InTransit, 'shipped', [
                'shipped_at' => Carbon::now('UTC'),
                'carrier' => filled($data['carrier'] ?? null) ? trim((string) $data['carrier']) : $locked->getAttribute('carrier'),
                'tracking_no' => filled($data['tracking_no'] ?? null) ? trim((string) $data['tracking_no']) : $locked->getAttribute('tracking_no'),
                'expected_delivery_on' => filled($data['expected_delivery_on'] ?? null) ? (string) $data['expected_delivery_on'] : $locked->getAttribute('expected_delivery_on')?->toDateString(),
            ]);
        });
    }

    /**
     * Teslim alindi. Miktar verilmeyen kalemde siparis miktarinin tamami alinmis sayilir.
     *
     * @param  array<int, float|int|string|null>  $receivedQuantities  Siparis kalemi kimligi => alinan miktar.
     */
    public function markDelivered(PurchaseOrder $order, array $receivedQuantities = []): PurchaseOrder
    {
        return $this->transactions->run(function () use ($order, $receivedQuantities): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = $this->lockForUpdate($order);
            $this->assertOpen($locked);

            if (! $locked->statusEnum()->isOnTheWay()) {
                throw InvalidTransitionException::make();
            }

            $this->receiveLines($locked, $receivedQuantities);

            return $this->transition($locked, PurchaseOrderStatus::Delivered, 'delivered', ['delivered_at' => Carbon::now('UTC')]);
        });
    }

    /**
     * Siparis tamamlandi. Onay gereken sipariste onay olmadan tamamlanamaz.
     * Kalemler tamamlanir; Sirket ici kalemler Sirket ici alimlar'a yazilir.
     */
    public function complete(PurchaseOrder $order, ?string $purchasedOn = null): PurchaseOrder
    {
        return $this->transactions->run(function () use ($order, $purchasedOn): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = $this->lockForUpdate($order);
            $this->assertOpen($locked);
            $this->assertApproved($locked);
            $now = Carbon::now('UTC');

            $this->receiveLines($locked, [], true);
            $this->transition($locked, PurchaseOrderStatus::Completed, 'completed', [
                'completed_by_personnel_id' => $this->actor->personnelId(),
                'completed_at' => $now,
                'ordered_at' => $locked->getAttribute('ordered_at') ?? $now,
                'ordered_by_personnel_id' => $locked->getAttribute('ordered_by_personnel_id') ?? $this->actor->personnelId(),
                'delivered_at' => $locked->getAttribute('delivered_at') ?? $now,
            ]);

            foreach ($this->needsOf($locked) as $need) {
                $this->needs->advance($need, PurchaseNeedStatus::Completed, 'completed', [
                    'completed_by_personnel_id' => $this->actor->personnelId(),
                    'completed_at' => $now,
                    'ordered_at' => $need->getAttribute('ordered_at') ?? $now,
                    'ordered_by_personnel_id' => $need->getAttribute('ordered_by_personnel_id') ?? $this->actor->personnelId(),
                ], ['siparis' => $locked->getAttribute('order_no')]);
            }

            $this->recordCompanyPurchases($locked, $purchasedOn);

            return $locked;
        });
    }

    /** Iptal: teklif de iptal olur; kalemler yeniden teklif toplanabilir. */
    public function cancel(PurchaseOrder $order, ?string $reason = null): PurchaseOrder
    {
        return $this->transactions->run(function () use ($order, $reason): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = $this->lockForUpdate($order);
            $this->assertOpen($locked);
            // Acik onay talebi kapanir; onay motoru siparisi Taslak'a cevirebilir, kayit tazelenir.
            $this->closeOpenApproval($locked);
            $locked->refresh();
            $needs = $this->needsOf($locked);

            $this->transition($locked, PurchaseOrderStatus::Cancelled, 'cancelled', ['cancelled_at' => Carbon::now('UTC')], filled($reason) ? ['gerekce' => $reason] : []);

            $quotation = $locked->quotation;

            if ($quotation instanceof PurchaseQuotation) {
                $this->quotations->markOrderCancelled($quotation, $reason);
            }

            foreach ($needs as $need) {
                $this->needs->revertToQuoting($need, (int) $locked->getAttribute('purchase_quotation_id'), true);
            }

            return $locked;
        });
    }

    public function archive(Model|int|string $record): PurchaseOrder
    {
        return $this->transactions->run(function () use ($record): PurchaseOrder {
            /** @var PurchaseOrder $order */
            $order = $this->lockForUpdate($record);

            if ($order->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $order->forceFill(['archived_at' => Carbon::now('UTC')]);
            $this->saveWithoutVersion($order);
            $this->recordActivity($order, 'archived');

            return $order;
        });
    }

    public function restore(Model|int|string $record): PurchaseOrder
    {
        return $this->transactions->run(function () use ($record): PurchaseOrder {
            /** @var PurchaseOrder $order */
            $order = $this->lockForUpdate($record);

            if (! $order->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $order->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($order);
            $this->recordActivity($order, 'restored');

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version']);

        foreach (['delivery_address', 'delivery_contact', 'delivery_from', 'delivery_until', 'expected_delivery_on', 'carrier', 'tracking_no', 'payment_terms', 'note', 'project_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? (is_string($data[$field]) ? trim($data[$field]) : $data[$field]) : null;
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        /** @var PurchaseOrder $record */
        return [
            'siparis_no' => $record->getAttribute('order_no'),
            'teklif' => $record->getAttribute('purchase_quotation_id'),
            'tedarikci' => $record->supplierLabel(),
            'tutar' => (float) $record->getAttribute('total_amount'),
            'para_birimi' => $record->getAttribute('currency_code'),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $extra
     */
    private function transition(PurchaseOrder $order, PurchaseOrderStatus $to, string $operation, array $attributes = [], array $extra = []): PurchaseOrder
    {
        $from = $order->statusEnum();
        $order->forceFill([...$attributes, 'status' => $to->value]);
        $this->saveWithoutVersion($order);
        $this->recordActivity($order, $operation, [...$extra, 'durum' => ['onceki' => $from->value, 'yeni' => $to->value]]);

        return $order;
    }

    /**
     * Siparisin kalemlerini (Satin alinacaklar) ayni asamaya tasir.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function moveNeeds(PurchaseOrder $order, PurchaseNeedStatus $to, string $operation, array $attributes = []): void
    {
        foreach ($this->needsOf($order) as $need) {
            if (! $need->statusEnum()->isClosed() && ! $need->isArchived()) {
                $this->needs->advance($need, $to, $operation, $attributes, ['siparis' => $order->getAttribute('order_no')]);
            }
        }
    }

    /**
     * @return list<PurchaseNeed>
     */
    private function needsOf(PurchaseOrder $order): array
    {
        $ids = $order->lines()->whereNotNull('purchase_need_id')->pluck('purchase_need_id')->map(static fn ($id): int => (int) $id)->unique()->all();

        return $ids === [] ? [] : PurchaseNeed::query()->whereIn('id', $ids)->lockForUpdate()->get()->all();
    }

    /**
     * @param  array<int, float|int|string|null>  $quantities
     */
    private function receiveLines(PurchaseOrder $order, array $quantities, bool $onlyMissing = false): void
    {
        foreach ($order->lines()->get() as $line) {
            /** @var PurchaseOrderLine $line */
            $id = (int) $line->getKey();

            if ($onlyMissing && (float) $line->getAttribute('received_quantity') > 0) {
                continue;
            }

            $value = array_key_exists($id, $quantities) && $quantities[$id] !== null && $quantities[$id] !== ''
                ? max(0.0, (float) $quantities[$id])
                : (float) $line->getAttribute('quantity');

            if ((float) $line->getAttribute('received_quantity') !== $value) {
                $line->forceFill(['received_quantity' => $value])->save();
            }
        }
    }

    /** Tamamlanan siparisin Sirket ici kalemleri Sirket ici alimlar'a yazilir (kalem basina bir kez). */
    private function recordCompanyPurchases(PurchaseOrder $order, ?string $purchasedOn): void
    {
        $date = $purchasedOn ?? Carbon::now(DisplayTime::zone())->toDateString();

        foreach ($order->lines()->with(['need.companyPurchase', 'catalogItem'])->get() as $line) {
            /** @var PurchaseOrderLine $line */
            $need = $line->need;
            $item = $line->catalogItem;
            $usage = $need instanceof PurchaseNeed ? $need->usageEnum() : ($item instanceof CatalogItem ? $item->usageEnum() : null);

            if ($usage !== UsageArea::Office || ! $item instanceof CatalogItem || ($need instanceof PurchaseNeed && $need->companyPurchase !== null)) {
                continue;
            }

            $quantity = (float) $line->getAttribute('received_quantity') > 0 ? (float) $line->getAttribute('received_quantity') : (float) $line->getAttribute('quantity');
            $netUnit = (float) $line->getAttribute('line_total') / max(0.0001, (float) $line->getAttribute('quantity'));

            $this->companyPurchases->create([
                'catalog_item_id' => $item->getKey(),
                'purchase_need_id' => $need?->getKey(),
                'purchase_request_id' => $need?->getAttribute('purchase_request_id'),
                'purchase_order_id' => $order->getKey(),
                'org_unit_id' => $need?->getAttribute('org_unit_id'),
                'personnel_id' => $need?->getAttribute('requested_by_personnel_id'),
                'supplier_party_id' => $order->getAttribute('supplier_party_id'),
                'supplier_name' => $order->getAttribute('supplier_name'),
                'description' => $line->getAttribute('description'),
                'purchased_on' => $date,
                'quantity' => $quantity > 0 ? $quantity : 1,
                'unit_price' => round($netUnit, 6),
                'currency_code' => $order->getAttribute('currency_code') ?? 'TRY',
                'renewal_months' => $item->getAttribute('renewal_months'),
            ]);
        }
    }

    private function assertOpen(PurchaseOrder $order): void
    {
        if ($order->isArchived() || $order->statusEnum()->isClosed()) {
            throw PurchaseOrderClosedException::make();
        }
    }

    private function assertApproved(PurchaseOrder $order): void
    {
        $state = $this->approvals->state($order);

        if ($state->required && ! $state->satisfied) {
            throw PurchaseApprovalRequiredException::make(['approvers' => $state->approvers === [] ? __('procurement.approval.policy_text') : $state->approversText()]);
        }
    }

    private function closeOpenApproval(PurchaseOrder $order): void
    {
        $request = $order->approvalRequest;

        if ($request instanceof ApprovalRequest && in_array($request->getAttribute('status'), [ApprovalRequestStatus::Pending, ApprovalRequestStatus::InProgress], true)) {
            $this->approvalRequests->cancel($request, __('procurement.approval.cancelled_reason'));
        }
    }

    private static function itemIdOf(PurchaseQuotationLine $line): ?int
    {
        $id = $line->getAttribute('catalog_item_id') ?? $line->need?->getAttribute('catalog_item_id');

        return $id !== null ? (int) $id : null;
    }

    private static function appendNote(mixed $current, mixed $addition): ?string
    {
        $addition = filled($addition) ? trim((string) $addition) : '';
        $current = filled($current) ? (string) $current : '';

        if ($addition === '') {
            return $current !== '' ? $current : null;
        }

        return $current === '' ? $addition : $current."\n".$addition;
    }
}
