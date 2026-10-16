<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Procurement\PurchaseRequestRequiredException;
use App\Services\Platform\SchemaReadiness;
use App\Exceptions\RecordArchivedException;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\CompanyPurchase;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sirket ici alimlar (B52, D-189; kullanici: "zaten alinmis olanlarin da kaydi
 * olmasi gerek; bazi hizmetlerin urunlerin yenilenme donemleri icin tarih
 * olacak (sunucu, claude, kalem, defter); olusacak giderler hesaplanabilecek").
 *
 * - D-190: yeni alim bir satin alma talebine baglidir (purchase_need_id
 *   zorunlu); ekran alanlari talepten doldurur.
 * - Yenileme donemi bos birakilirsa katalog kaleminin donemi kullanilir;
 *   sonraki yenileme tarihi verilmezse alim tarihi + donemdir.
 * - renew(): yenileme yapildi; sonraki tarih bir donem ileri alinir.
 * - archive / restore (D-156).
 */
final class CompanyPurchaseService extends AbstractService
{
    protected string $orderBy = 'purchased_on';

    protected string $orderDirection = 'desc';

    /** @var list<string> */
    private const FIELDS = [
        'catalog_item_id', 'purchase_need_id', 'org_unit_id', 'personnel_id', 'supplier_party_id', 'supplier_name',
        'description', 'purchased_on', 'quantity', 'unit_price', 'currency_code', 'renewal_months', 'next_renewal_on',
        'ends_on', 'note',
        // D-192 (B53)
        'purchase_request_id', 'purchase_order_id',
    ];

    /** @var list<string> Kalemli alimda her satira ortak baslik alanlari (D-192). */
    private const HEADER = [
        'purchase_request_id', 'org_unit_id', 'personnel_id', 'supplier_party_id', 'supplier_name', 'purchased_on',
        'currency_code', 'note',
    ];

    public function update(Model|int|string $record, array $data): Model
    {
        $current = $this->show($record);

        if ($current instanceof CompanyPurchase && $current->isArchived()) {
            throw RecordArchivedException::make();
        }

        unset($data['purchase_need_id'], $data['purchase_request_id'], $data['purchase_order_id']);

        return parent::update($record, $data);
    }

    /**
     * Kalemli sirket ici alim (D-192; kullanici: "yine sirket ici alimlar kismi
     * da ayni tarz bir arayuz"): baslik (talep, birim, kisi, tedarikci, tarih,
     * para birimi) her kaleme yazilir; her kalem ayri bir alim satiridir
     * (yenileme donemi kaleme gore). Talebin kalemi secilmisse (`purchase_need_id`)
     * o kaleme baglanir.
     *
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines  catalog_item_id, purchase_need_id, description, quantity, unit_price, renewal_months, next_renewal_on, ends_on
     * @return list<CompanyPurchase>
     */
    public function createLines(array $header, array $lines): array
    {
        return $this->transactions->run(function () use ($header, $lines): array {
            $common = array_intersect_key($header, array_flip(self::HEADER));
            $created = [];

            foreach ($lines as $line) {
                if (! is_array($line) || blank($line['catalog_item_id'] ?? null)) {
                    continue;
                }

                /** @var CompanyPurchase $purchase */
                $purchase = $this->create([
                    ...$common,
                    ...array_intersect_key($line, array_flip(['catalog_item_id', 'purchase_need_id', 'description', 'quantity', 'unit_price', 'renewal_months', 'next_renewal_on', 'ends_on'])),
                    'note' => filled($line['note'] ?? null) ? $line['note'] : ($common['note'] ?? null),
                ]);
                $created[] = $purchase;
            }

            if ($created === []) {
                throw PurchaseRequestRequiredException::make();
            }

            return $created;
        });
    }

    /** Yenileme yapildi: sonraki yenileme tarihi bir donem ileri alinir. */
    public function renew(Model|int|string $record): CompanyPurchase
    {
        return $this->transactions->run(function () use ($record): CompanyPurchase {
            /** @var CompanyPurchase $purchase */
            $purchase = $this->lockForUpdate($record);
            $months = (int) $purchase->getAttribute('renewal_months');
            $next = $purchase->getAttribute('next_renewal_on');

            if ($purchase->isArchived() || $months <= 0 || $next === null) {
                throw InvalidTransitionException::make();
            }

            $previous = $next->toDateString();
            $purchase->forceFill(['next_renewal_on' => $next->copy()->addMonthsNoOverflow($months)->toDateString()]);
            $this->saveWithoutVersion($purchase);
            $this->recordActivity($purchase, 'renewed', ['yenileme' => $previous, 'sonraki' => $purchase->getAttribute('next_renewal_on')?->toDateString()]);

            return $purchase;
        });
    }

    public function archive(Model|int|string $record): CompanyPurchase
    {
        return $this->transactions->run(function () use ($record): CompanyPurchase {
            /** @var CompanyPurchase $purchase */
            $purchase = $this->lockForUpdate($record);

            if ($purchase->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $purchase->forceFill(['archived_at' => Carbon::now('UTC')]);
            $this->saveWithoutVersion($purchase);
            $this->recordActivity($purchase, 'archived');

            return $purchase;
        });
    }

    public function restore(Model|int|string $record): CompanyPurchase
    {
        return $this->transactions->run(function () use ($record): CompanyPurchase {
            /** @var CompanyPurchase $purchase */
            $purchase = $this->lockForUpdate($record);

            if (! $purchase->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $purchase->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($purchase);
            $this->recordActivity($purchase, 'restored');

            return $purchase;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        $values = array_intersect_key($data, array_flip(self::FIELDS));

        if (! SchemaReadiness::hasBatch('B53')) {
            unset($values['purchase_request_id'], $values['purchase_order_id']);
        }

        foreach (['org_unit_id', 'personnel_id', 'supplier_party_id', 'supplier_name', 'description', 'next_renewal_on', 'ends_on', 'note', 'renewal_months', 'purchase_need_id', 'purchase_request_id', 'purchase_order_id'] as $field) {
            if (array_key_exists($field, $values) && blank($values[$field])) {
                $values[$field] = null;
            }
        }

        if (filled($values['supplier_party_id'] ?? null)) {
            $values['supplier_name'] = null;
        }

        if (array_key_exists('quantity', $values) && (float) $values['quantity'] <= 0) {
            $values['quantity'] = 1;
        }

        if (array_key_exists('currency_code', $values)) {
            $values['currency_code'] = strtoupper((string) ($values['currency_code'] ?: 'TRY'));
        }

        // D-190 (kullanici: "Sirket ici alim ekle ayni zamanda bir talebe bagli olmalidir").
        // D-192: kalemli talebin kendisi (purchase_request_id) ya da siparis de baglantidir.
        $linked = filled($values['purchase_need_id'] ?? null) || filled($values['purchase_request_id'] ?? null) || filled($values['purchase_order_id'] ?? null);

        if ($record === null && SchemaReadiness::hasBatch('B52A') && ! $linked) {
            throw PurchaseRequestRequiredException::make();
        }

        if ($record === null) {
            $itemId = (int) ($values['catalog_item_id'] ?? 0);
            $months = $values['renewal_months'] ?? null;

            if ($months === null) {
                $months = CatalogItem::query()->whereKey($itemId)->value('renewal_months');
                $values['renewal_months'] = $months !== null ? (int) $months : null;
            }

            if (($values['next_renewal_on'] ?? null) === null && $values['renewal_months'] !== null && filled($values['purchased_on'] ?? null)) {
                $values['next_renewal_on'] = Carbon::parse((string) $values['purchased_on'])->addMonthsNoOverflow((int) $values['renewal_months'])->toDateString();
            }
        }

        if (array_key_exists('renewal_months', $values) && $values['renewal_months'] !== null) {
            $values['renewal_months'] = (int) $values['renewal_months'] > 0 ? (int) $values['renewal_months'] : null;
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return [
            'kalem' => $record->getAttribute('catalog_item_id'),
            'aciklama' => Str::limit((string) $record->getAttribute('description'), 120),
            'alim_tarihi' => $record->getAttribute('purchased_on')?->toDateString(),
        ];
    }
}
