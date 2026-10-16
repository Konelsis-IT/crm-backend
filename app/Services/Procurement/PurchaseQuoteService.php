<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Exceptions\Procurement\PurchaseNeedClosedException;
use App\Exceptions\Procurement\PurchaseQuoteRateRequiredException;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuote;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Tedarikci teklifleri (B52, D-189): "Siparisi gecilecek urunlerin teklifleri
 * birden fazla olabilir; her teklif ekranda gorulebilir ve karsilastirilabilir
 * olacak." Toplam = miktar x birim fiyat (formdan gelmez). Dovizli teklifte
 * TL kuru zorunlu (onay siniri TL). Ilk teklif kalemi "Teklif toplaniyor"
 * yapar; onerilen teklif degisir ya da silinirse onay yeniden istenir. Siparisi
 * verilmis / kapanmis kalemin teklifleri degismez.
 */
final class PurchaseQuoteService extends AbstractService
{
    /** @var list<string> */
    private const FIELDS = [
        'purchase_need_id', 'supplier_party_id', 'supplier_name', 'brand_model', 'quantity', 'unit_price', 'currency_code',
        'try_rate', 'lead_time_days', 'delivery_on', 'payment_terms', 'valid_until', 'note',
    ];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly Container $container,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $need = $this->openNeed((int) ($data['purchase_need_id'] ?? 0));
            $quote = parent::create([...$data, 'quantity' => $data['quantity'] ?? $need->getAttribute('quantity')]);
            $this->needs()->markQuoting($need);

            return $quote;
        });
    }

    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var PurchaseQuote $current */
            $current = $this->lockForUpdate($record);
            $need = $this->openNeed((int) $current->getAttribute('purchase_need_id'));
            unset($data['purchase_need_id']);

            /** @var PurchaseQuote $quote */
            $quote = parent::update($current, $data);

            if ((int) $need->getAttribute('recommended_quote_id') === (int) $quote->getKey()
                && in_array($need->statusEnum(), [PurchaseNeedStatus::AwaitingApproval, PurchaseNeedStatus::Approved], true)) {
                // Onaylanan / onaya giden teklif degisti: onay yeniden istenir.
                $this->needs()->forgetRecommendation($need);
            }

            return $quote;
        });
    }

    public function delete(Model|int|string $record): bool
    {
        return $this->transactions->run(function () use ($record): bool {
            /** @var PurchaseQuote $quote */
            $quote = $this->lockForUpdate($record);
            $need = $this->openNeed((int) $quote->getAttribute('purchase_need_id'));
            $wasRecommended = (int) $need->getAttribute('recommended_quote_id') === (int) $quote->getKey();

            if ($wasRecommended) {
                $need->forceFill(['recommended_quote_id' => null])->saveQuietly();
            }

            $deleted = parent::delete($quote);

            if ($wasRecommended) {
                $this->needs()->forgetRecommendation($need);
            }

            return $deleted;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        $values = array_intersect_key($data, array_flip(self::FIELDS));
        $quantity = (float) ($values['quantity'] ?? $record?->getAttribute('quantity') ?? 1);
        $unitPrice = (float) ($values['unit_price'] ?? $record?->getAttribute('unit_price') ?? 0);
        $currency = strtoupper((string) ($values['currency_code'] ?? $record?->getAttribute('currency_code') ?? 'TRY'));
        $rate = $values['try_rate'] ?? $record?->getAttribute('try_rate');

        if ($currency !== 'TRY' && (blank($rate) || (float) $rate <= 0)) {
            throw PurchaseQuoteRateRequiredException::make();
        }

        $values['quantity'] = $quantity > 0 ? $quantity : 1;
        $values['currency_code'] = $currency;
        $values['try_rate'] = $currency === 'TRY' ? null : (float) $rate;
        $values['total_amount'] = round($values['quantity'] * $unitPrice, 4);

        foreach (['supplier_party_id', 'supplier_name', 'brand_model', 'lead_time_days', 'delivery_on', 'payment_terms', 'valid_until', 'note'] as $field) {
            if (array_key_exists($field, $values) && blank($values[$field])) {
                $values[$field] = null;
            }
        }

        if (filled($values['supplier_party_id'] ?? null)) {
            $values['supplier_name'] = null;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        /** @var PurchaseQuote $record */
        return [
            'tedarikci' => $record->supplierLabel(),
            'tutar' => (float) $record->getAttribute('total_amount'),
            'para_birimi' => $record->getAttribute('currency_code'),
            'siparis_kalemi' => $record->getAttribute('purchase_need_id'),
        ];
    }

    private function openNeed(int $id): PurchaseNeed
    {
        /** @var PurchaseNeed $need */
        $need = PurchaseNeed::query()->lockForUpdate()->findOrFail($id);

        if ($need->isArchived() || $need->statusEnum()->isClosed() || $need->statusEnum() === PurchaseNeedStatus::Ordered) {
            throw PurchaseNeedClosedException::make();
        }

        if ($need->statusEnum() === PurchaseNeedStatus::AwaitingProject) {
            throw \App\Exceptions\Procurement\ProjectApprovalPendingException::make();
        }

        return $need;
    }

    private function needs(): PurchaseNeedService
    {
        return $this->container->make(PurchaseNeedService::class);
    }
}
