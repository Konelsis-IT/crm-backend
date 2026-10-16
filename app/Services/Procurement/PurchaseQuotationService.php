<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseQuotationStatus;
use App\Enums\Procurement\QuotationAiStatus;
use App\Enums\Procurement\QuotationDocumentAiStatus;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Procurement\PurchaseNeedNotQuotableException;
use App\Exceptions\Procurement\PurchaseQuotationEmptyException;
use App\Exceptions\Procurement\PurchaseQuotationLockedException;
use App\Exceptions\Procurement\PurchaseQuoteRateRequiredException;
use App\Exceptions\RecordNotFoundException;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationDocument;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Query\Document\FixedDocumentQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentService;
use App\Services\Numbering\YearlyCodeAllocator;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Procurement\UnitResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Satin alma teklifleri (D-192, B53; kullanici 12 Ekim 2026: "daha kapsamlisi
 * 'Satin alma teklifleri' kismi olusturulup orada tutulmali ... topluca
 * arayuzden checkbox olarak secilip topluca teklif'te birlestirilip ortak bir
 * siparise donusturulebilmesi").
 *
 * - Teklif bir tedarikcinin kalemli teklifidir; kod STKF-YYYY-NNNN.
 * - `requestFromNeeds`: Satin alinacaklar'da secilen kalemler tek teklifte
 *   toplanir; birden fazla tedarikci secilirse her birine ayri taslak acilir
 *   (karsilastirma icin). Kalem Teklif toplaniyor olur.
 * - Kalem toplamlari ve teklif toplamlari formdan gelmez, burada hesaplanir.
 *   Fiyati girilmis kalem varsa taslak Alindi olur.
 * - `select`: satin alma en uygun teklifi secer; kalemleri Onerilen olur, ayni
 *   kalemlerin daha once secilmis teklifi Alindi'ya doner. Dovizli teklifte
 *   TL kuru zorunludur (onay siniri TL).
 * - Belgeler Dokuman yonetiminde (tur STF) acilir ve teklife baglanir;
 *   Konelsis AI sonucu `applyExtraction` ile alanlara yazilir (bos alanlar
 *   doldurulur, ayni katalog kalemli fiyatsiz satir fiyatlanir, digerleri
 *   eklenir).
 * - Siparise donusmus / reddedilmis / iptal teklif degismez. Teklif silinmez,
 *   arsive alinir; kalem ve belge baglantisi (alt satir) silinebilir.
 */
final class PurchaseQuotationService extends AbstractService
{
    public const DOCUMENT_TYPE = 'STF';

    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    /** @var list<string> Baslik alanlari. */
    private const HEADER = [
        'supplier_party_id', 'supplier_name', 'supplier_reference', 'quote_date', 'valid_until', 'currency_code', 'try_rate',
        'payment_terms', 'delivery_terms', 'lead_time_days', 'delivery_on', 'general_discount', 'note',
    ];

    /** @var list<string> Kalem alanlari. */
    private const LINE = [
        'purchase_need_id', 'catalog_item_id', 'description', 'brand_model', 'quantity', 'unit', 'unit_price',
        'discount_rate', 'is_option', 'is_cancelled', 'ai_created_item', 'note',
        // D-194 (B54): sabit birim (UnitResolver::apply)
        'uom_id',
    ];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly YearlyCodeAllocator $codes,
        private readonly PurchaseNeedService $needs,
        private readonly DocumentService $documents,
        private readonly FixedDocumentQueries $fixedDocuments,
        private readonly UnitResolver $units,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data  Baslik alanlari + `lines`.
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): PurchaseQuotation {
            /** @var PurchaseQuotation $quotation */
            $quotation = parent::create([
                ...array_intersect_key($data, array_flip(self::HEADER)),
                'quotation_no' => $this->codes->next('STKF')['formatted_code'],
                'status' => PurchaseQuotationStatus::Draft->value,
            ]);

            $this->syncLines($quotation, is_array($data['lines'] ?? null) ? $data['lines'] : []);
            $this->refreshTotals($quotation);

            return $quotation->refresh();
        });
    }

    /**
     * "Teklif iste" (Satin alinacaklar toplu islemi): secilen kalemler bir
     * teklifte toplanir. Tedarikci listesi verilirse her tedarikciye ayri
     * taslak acilir; bos ise tedarikcisiz tek taslak.
     *
     * @param  iterable<PurchaseNeed>  $needs
     * @param  array<string, mixed>  $header
     * @param  list<int>  $supplierPartyIds
     * @return list<PurchaseQuotation>
     */
    public function requestFromNeeds(iterable $needs, array $header = [], array $supplierPartyIds = []): array
    {
        return $this->transactions->run(function () use ($needs, $header, $supplierPartyIds): array {
            $lines = [];

            foreach ($needs as $need) {
                if ($need instanceof PurchaseNeed) {
                    $lines[] = $this->lineFromNeed($this->quotableNeed($need));
                }
            }

            if ($lines === []) {
                throw PurchaseQuotationEmptyException::make();
            }

            $suppliers = array_values(array_unique(array_filter(array_map('intval', $supplierPartyIds))));
            $created = [];

            foreach ($suppliers === [] ? [null] : $suppliers as $supplierId) {
                /** @var PurchaseQuotation $quotation */
                $quotation = $this->create([...$header, 'supplier_party_id' => $supplierId, 'lines' => $lines]);
                $created[] = $quotation;
            }

            return $created;
        });
    }

    /**
     * Var olan (degisebilen) teklife Satin alinacaklar kalemleri ekler; teklifte
     * zaten olan kalem atlanir. Eklenen kalem sayisini doner.
     *
     * @param  iterable<PurchaseNeed>  $needs
     */
    public function addNeeds(PurchaseQuotation $quotation, iterable $needs): int
    {
        return $this->transactions->run(function () use ($quotation, $needs): int {
            /** @var PurchaseQuotation $locked */
            $locked = $this->lockForUpdate($quotation);
            $this->assertEditable($locked);
            $present = $locked->lines()->whereNotNull('purchase_need_id')->pluck('purchase_need_id')->map(static fn ($id): int => (int) $id)->all();
            $sort = (int) $locked->lines()->max('sort_order') + 1;
            $added = 0;

            foreach ($needs as $need) {
                if (! $need instanceof PurchaseNeed || in_array((int) $need->getKey(), $present, true)) {
                    continue;
                }

                $this->createLine($locked, $this->lineFromNeed($this->quotableNeed($need)), $sort++);
                $added++;
            }

            $this->refreshTotals($locked);

            return $added;
        });
    }

    /**
     * Baslik ve (verilmisse) kalemler: `id`'li kalem guncellenir, yeni kalem
     * eklenir, formdan kaldirilan kalem silinir.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): PurchaseQuotation {
            /** @var PurchaseQuotation $quotation */
            $quotation = $this->lockForUpdate($record);
            $this->assertEditable($quotation);

            /** @var PurchaseQuotation $quotation */
            $quotation = parent::update($quotation, array_intersect_key($data, array_flip([...self::HEADER, 'row_version'])));

            if (array_key_exists('lines', $data)) {
                $this->syncLines($quotation, is_array($data['lines']) ? $data['lines'] : []);
            }

            $this->refreshTotals($quotation);

            return $quotation->refresh();
        });
    }

    /** En uygun teklif olarak secer. */
    public function select(PurchaseQuotation $quotation): PurchaseQuotation
    {
        return $this->transactions->run(function () use ($quotation): PurchaseQuotation {
            /** @var PurchaseQuotation $locked */
            $locked = $this->lockForUpdate($quotation);
            $this->assertEditable($locked);

            if ($locked->statusEnum() === PurchaseQuotationStatus::Selected) {
                return $locked;
            }

            if ($locked->statusEnum() !== PurchaseQuotationStatus::Received || (float) $locked->getAttribute('total_amount') <= 0) {
                throw PurchaseQuotationEmptyException::make();
            }

            if ($locked->totalInTry() === null) {
                throw PurchaseQuoteRateRequiredException::make();
            }

            $needIds = $this->needIdsOf($locked);

            // Ayni kalemler icin daha once secilmis teklif Alindi'ya doner.
            $others = PurchaseQuotation::query()
                ->whereKeyNot($locked->getKey())
                ->where('status', PurchaseQuotationStatus::Selected->value)
                ->whereHas('lines', fn ($line) => $line->whereIn('purchase_need_id', $needIds === [] ? [0] : $needIds))
                ->lockForUpdate()
                ->get();

            foreach ($others as $other) {
                /** @var PurchaseQuotation $other */
                $this->transition($other, PurchaseQuotationStatus::Received, 'unselected', ['selected_by_personnel_id' => null, 'selected_at' => null]);
            }

            $this->transition($locked, PurchaseQuotationStatus::Selected, 'selected', [
                'selected_by_personnel_id' => $this->actor->personnelId(),
                'selected_at' => Carbon::now('UTC'),
            ], ['tedarikci' => $locked->supplierLabel(), 'tutar' => (float) $locked->getAttribute('total_amount'), 'para_birimi' => $locked->getAttribute('currency_code')]);

            foreach ($others as $other) {
                foreach ($this->needIdsOf($other) as $needId) {
                    if (! in_array($needId, $needIds, true) && ($need = PurchaseNeed::query()->find($needId)) instanceof PurchaseNeed) {
                        $this->needs->revertToQuoting($need);
                    }
                }
            }

            foreach (PurchaseNeed::query()->whereIn('id', $needIds)->get() as $need) {
                /** @var PurchaseNeed $need */
                if (in_array($need->statusEnum(), [PurchaseNeedStatus::Open, PurchaseNeedStatus::Quoting, PurchaseNeedStatus::Recommended], true)) {
                    $this->needs->advance($need, PurchaseNeedStatus::Recommended, 'recommended', [
                        'recommended_by_personnel_id' => $this->actor->personnelId(),
                        'recommended_at' => Carbon::now('UTC'),
                    ], ['teklif' => $locked->getAttribute('quotation_no'), 'tedarikci' => $locked->supplierLabel()]);
                }
            }

            return $locked;
        });
    }

    /** Secimi geri alir (siparis acilmadiysa). */
    public function unselect(PurchaseQuotation $quotation): PurchaseQuotation
    {
        return $this->transactions->run(function () use ($quotation): PurchaseQuotation {
            /** @var PurchaseQuotation $locked */
            $locked = $this->lockForUpdate($quotation);

            if ($locked->statusEnum() !== PurchaseQuotationStatus::Selected) {
                throw InvalidTransitionException::make();
            }

            $this->transition($locked, PurchaseQuotationStatus::Received, 'unselected', ['selected_by_personnel_id' => null, 'selected_at' => null]);
            $this->releaseNeeds($locked, null);

            return $locked;
        });
    }

    public function reject(PurchaseQuotation $quotation, ?string $reason = null): PurchaseQuotation
    {
        return $this->close($quotation, PurchaseQuotationStatus::Rejected, 'rejected', $reason);
    }

    public function cancel(PurchaseQuotation $quotation, ?string $reason = null): PurchaseQuotation
    {
        return $this->close($quotation, PurchaseQuotationStatus::Cancelled, 'cancelled', $reason);
    }

    /**
     * Siparis acilinca (PurchaseOrderService) teklif Siparis verildi olur;
     * siparis iptal edilince teklif de iptal olur (ayni teklif ikinci kez
     * siparise donusmez).
     */
    public function markOrdered(PurchaseQuotation $quotation): void
    {
        $this->transition($quotation, PurchaseQuotationStatus::Ordered, 'ordered');
    }

    public function markOrderCancelled(PurchaseQuotation $quotation, ?string $reason): void
    {
        $this->transition($quotation, PurchaseQuotationStatus::Cancelled, 'order_cancelled', [], filled($reason) ? ['gerekce' => $reason] : []);
    }

    public function archive(Model|int|string $record): PurchaseQuotation
    {
        return $this->transactions->run(function () use ($record): PurchaseQuotation {
            /** @var PurchaseQuotation $quotation */
            $quotation = $this->lockForUpdate($record);

            if ($quotation->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $quotation->forceFill(['archived_at' => Carbon::now('UTC')]);
            $this->saveWithoutVersion($quotation);
            $this->recordActivity($quotation, 'archived');

            return $quotation;
        });
    }

    public function restore(Model|int|string $record): PurchaseQuotation
    {
        return $this->transactions->run(function () use ($record): PurchaseQuotation {
            /** @var PurchaseQuotation $quotation */
            $quotation = $this->lockForUpdate($record);

            if (! $quotation->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $quotation->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($quotation);
            $this->recordActivity($quotation, 'restored');

            return $quotation;
        });
    }

    /**
     * Teklif belgelerini yukler: her dosya Dokuman yonetiminde (tur STF) yeni
     * belge + ilk revizyon olur ve teklife baglanir. Konelsis AI calisacaksa
     * belge "AI bekliyor" isaretlenir.
     *
     * @param  list<array{path: string, name: string|null}>  $files  Gecici yukleme yollari.
     * @return list<PurchaseQuotationDocument>
     */
    public function attachDocuments(PurchaseQuotation $quotation, array $files, bool $forAi = false): array
    {
        return $this->transactions->run(function () use ($quotation, $files, $forAi): array {
            /** @var PurchaseQuotation $locked */
            $locked = $this->lockForUpdate($quotation);
            $this->assertEditable($locked);
            $typeId = $this->fixedDocuments->documentTypeId(self::DOCUMENT_TYPE) ?? throw RecordNotFoundException::make();
            $attached = [];

            foreach ($files as $file) {
                $path = trim((string) ($file['path'] ?? ''));

                if ($path === '') {
                    continue;
                }

                $name = filled($file['name'] ?? null) ? (string) $file['name'] : basename($path);
                $document = $this->documents->createWithInitialRevision([
                    'document_type_id' => $typeId,
                    'title' => Str::limit($locked->getAttribute('quotation_no').' – '.$name, 250, ''),
                    'owner_personnel_id' => $this->actor->personnelId(),
                    'default_language' => 'tr',
                    'file_temp_path' => $path,
                    'file_original_name' => $name,
                ]);
                $revisionId = $document->refresh()->displayRevision()?->getKey() ?? throw RecordNotFoundException::make();

                /** @var PurchaseQuotationDocument $row */
                $row = PurchaseQuotationDocument::query()->create([
                    'purchase_quotation_id' => $locked->getKey(),
                    'document_revision_id' => $revisionId,
                    'ai_status' => $forAi ? QuotationDocumentAiStatus::Pending->value : null,
                ]);
                $attached[] = $row;
            }

            if ($attached !== []) {
                $this->recordActivity($locked, 'documents_attached', ['belge' => count($attached)]);
            }

            return $attached;
        });
    }

    /** Belge baglantisini kaldirir; dosya Dokumanlar'da kalir. */
    public function detachDocument(PurchaseQuotationDocument $document): void
    {
        $this->transactions->run(function () use ($document): void {
            /** @var PurchaseQuotation $quotation */
            $quotation = $this->lockForUpdate((int) $document->getAttribute('purchase_quotation_id'));
            $this->assertEditable($quotation);
            $document->delete();
            $this->recordActivity($quotation, 'document_detached', ['revizyon' => $document->getAttribute('document_revision_id')]);
        });
    }

    /** Konelsis AI isinin durumu (teklif duzeyi). */
    public function setAiStatus(PurchaseQuotation $quotation, ?QuotationAiStatus $status, ?string $jobReference = null): void
    {
        $quotation->forceFill([
            'ai_status' => $status?->value,
            'ai_job_reference' => $jobReference ?? $quotation->getAttribute('ai_job_reference'),
            'ai_requested_at' => $status === QuotationAiStatus::Pending ? Carbon::now('UTC') : $quotation->getAttribute('ai_requested_at'),
        ]);
        $this->saveWithoutVersion($quotation);
    }

    /** Bir belgenin Konelsis AI sonucu (teklif mi, kisa ozet). */
    public function setDocumentAiResult(PurchaseQuotationDocument $document, QuotationDocumentAiStatus $status, ?string $summary = null): void
    {
        $document->forceFill(['ai_status' => $status->value, 'ai_summary' => filled($summary) ? Str::limit(trim($summary), 2000, '') : null])->save();
    }

    /**
     * Konelsis AI'nin bir teklif belgesinden okudugu bilgileri yazar (D-192).
     * Baslikta yalniz bos alanlar doldurulur (kullanicinin girdigi deger ezilmez).
     * Kalemler: ayni katalog kalemli ve fiyati girilmemis satir fiyatlanir;
     * digerleri yeni satir olarak eklenir. Katalog kalemlerini cagiran taraf
     * (Malzeme ve Hizmetler eslesmesi / yeni kalem) cozer.
     *
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     * @return array{filled_fields: list<string>, priced: int, added: int}
     */
    public function applyExtraction(PurchaseQuotation $quotation, array $header, array $lines): array
    {
        return $this->transactions->run(function () use ($quotation, $header, $lines): array {
            /** @var PurchaseQuotation $locked */
            $locked = $this->lockForUpdate($quotation);
            $this->assertEditable($locked);
            $fill = [];

            foreach (array_intersect_key($header, array_flip(self::HEADER)) as $field => $value) {
                $current = $locked->getAttribute($field);
                $empty = $current === null || $current === '' || ($field === 'general_discount' && (float) $current === 0.0);

                if ($empty && filled($value)) {
                    $fill[$field] = $value;
                }
            }

            if (filled($fill['supplier_party_id'] ?? null) && filled($locked->getAttribute('supplier_name'))) {
                unset($fill['supplier_party_id']);
            }

            if ($fill !== []) {
                parent::update($locked, $fill);
                $locked->refresh();
            }

            $priced = 0;
            $added = 0;
            $sort = (int) $locked->lines()->max('sort_order') + 1;

            foreach ($lines as $line) {
                $values = array_intersect_key($line, array_flip(self::LINE));

                if (blank($values['description'] ?? null) && blank($values['catalog_item_id'] ?? null)) {
                    continue;
                }

                $target = filled($values['catalog_item_id'] ?? null)
                    ? $locked->lines()->where('catalog_item_id', (int) $values['catalog_item_id'])->where('unit_price', 0)->first()
                    : null;

                if ($target instanceof PurchaseQuotationLine) {
                    $this->updateLine($target, [
                        'unit_price' => $values['unit_price'] ?? 0,
                        'discount_rate' => $values['discount_rate'] ?? $target->getAttribute('discount_rate'),
                        'brand_model' => $target->getAttribute('brand_model') ?? ($values['brand_model'] ?? null),
                        'is_option' => $values['is_option'] ?? $target->getAttribute('is_option'),
                    ]);
                    $priced++;

                    continue;
                }

                $this->createLine($locked, $values, $sort++);
                $added++;
            }

            $this->refreshTotals($locked);
            $this->recordActivity($locked, 'ai_applied', ['alan' => array_keys($fill), 'fiyatlanan' => $priced, 'eklenen' => $added]);

            return ['filled_fields' => array_keys($fill), 'priced' => $priced, 'added' => $added];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version'], $data['lines']);

        if (array_key_exists('currency_code', $data) || $record === null) {
            $data['currency_code'] = strtoupper((string) (($data['currency_code'] ?? null) ?: ($record?->getAttribute('currency_code') ?? 'TRY')));
        }

        $currency = (string) ($data['currency_code'] ?? $record?->getAttribute('currency_code') ?? 'TRY');

        if ($currency === 'TRY') {
            $data['try_rate'] = null;
        } elseif (array_key_exists('try_rate', $data)) {
            $data['try_rate'] = filled($data['try_rate']) && (float) $data['try_rate'] > 0 ? (float) $data['try_rate'] : null;
        }

        foreach (['supplier_party_id', 'supplier_name', 'supplier_reference', 'quote_date', 'valid_until', 'payment_terms', 'delivery_terms', 'lead_time_days', 'delivery_on', 'note'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? (is_string($data[$field]) ? trim($data[$field]) : $data[$field]) : null;
            }
        }

        if (filled($data['supplier_party_id'] ?? null)) {
            $data['supplier_name'] = null;
        }

        if (array_key_exists('general_discount', $data)) {
            $data['general_discount'] = max(0.0, (float) ($data['general_discount'] ?? 0));
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        /** @var PurchaseQuotation $record */
        return [
            'teklif_no' => $record->getAttribute('quotation_no'),
            'tedarikci' => $record->supplierLabel(),
            'para_birimi' => $record->getAttribute('currency_code'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncLines(PurchaseQuotation $quotation, array $lines): void
    {
        $existing = $quotation->lines()->get()->keyBy(static fn (PurchaseQuotationLine $line): int => (int) $line->getKey());
        $kept = [];
        $sort = 0;

        foreach ($lines as $line) {
            if (! is_array($line) || (blank($line['description'] ?? null) && blank($line['catalog_item_id'] ?? null) && blank($line['purchase_need_id'] ?? null))) {
                continue;
            }

            $id = filled($line['id'] ?? null) ? (int) $line['id'] : null;
            $row = $id !== null ? $existing->get($id) : null;

            if ($row instanceof PurchaseQuotationLine) {
                $kept[] = $id;
                $this->updateLine($row, [...array_intersect_key($line, array_flip(self::LINE)), 'sort_order' => $sort++]);

                continue;
            }

            $created = $this->createLine($quotation, array_intersect_key($line, array_flip(self::LINE)), $sort++);
            $kept[] = (int) $created->getKey();
        }

        foreach ($existing as $id => $row) {
            if (! in_array($id, $kept, true)) {
                $needId = $row->getAttribute('purchase_need_id');
                $row->delete();

                if ($needId !== null && ($need = PurchaseNeed::query()->find((int) $needId)) instanceof PurchaseNeed) {
                    $this->needs->revertToQuoting($need, (int) $quotation->getKey());
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function createLine(PurchaseQuotation $quotation, array $values, int $sort): PurchaseQuotationLine
    {
        $need = null;

        if (filled($values['purchase_need_id'] ?? null)) {
            /** @var PurchaseNeed $need */
            $need = $this->quotableNeed(PurchaseNeed::query()->findOrFail((int) $values['purchase_need_id']));
        }

        /** @var CatalogItem|null $item */
        $item = filled($values['catalog_item_id'] ?? null) ? CatalogItem::query()->find((int) $values['catalog_item_id']) : null;
        $description = trim((string) ($values['description'] ?? ''));

        /** @var PurchaseQuotationLine $line */
        $line = PurchaseQuotationLine::query()->create([
            ...$this->normalizedLine($values),
            'purchase_quotation_id' => $quotation->getKey(),
            'purchase_need_id' => $need?->getKey(),
            'catalog_item_id' => $item?->getKey() ?? $need?->getAttribute('catalog_item_id'),
            'description' => Str::limit($description !== '' ? $description : (string) ($item?->getAttribute('name') ?? $need?->getAttribute('title') ?? '–'), 495, ''),
            // D-194 (B54): kalemin birimi sabittir; yoksa secilen / satin alinacak kalemin birimi ya da metinden cozulen.
            ...$this->units->apply([
                'uom_id' => filled($values['uom_id'] ?? null) ? $values['uom_id'] : (UnitResolver::enabled() ? $need?->getAttribute('uom_id') : null),
                'unit' => filled($values['unit'] ?? null) ? $values['unit'] : ($need?->getAttribute('unit') ?? $item?->getAttribute('unit')),
            ], $item ?? $need?->catalogItem),
            'sort_order' => $sort,
        ]);

        if ($need instanceof PurchaseNeed) {
            $this->needs->markQuoting($need);
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function updateLine(PurchaseQuotationLine $line, array $values): void
    {
        unset($values['purchase_need_id']);

        if (array_key_exists('description', $values) && blank($values['description'])) {
            unset($values['description']);
        }

        // D-194 (B54): birim ya da kalem degisince birim esitlenir (kalemin birimi sabittir).
        if (array_key_exists('uom_id', $values) || array_key_exists('unit', $values) || array_key_exists('catalog_item_id', $values)) {
            $itemId = array_key_exists('catalog_item_id', $values) ? $values['catalog_item_id'] : $line->getAttribute('catalog_item_id');
            $item = filled($itemId) ? CatalogItem::query()->find((int) $itemId) : null;
            $values = $this->units->apply($values, $item instanceof CatalogItem ? $item : null);
        }

        $line->fill([...$line->only(['quantity', 'unit_price', 'discount_rate']), ...$this->normalizedLine([...$line->only(['quantity', 'unit_price', 'discount_rate']), ...$values])]);

        if ($line->isDirty()) {
            $line->save();
        }
    }

    /**
     * Sayilar, isaretler ve satir toplami.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizedLine(array $values): array
    {
        $quantity = (float) ($values['quantity'] ?? 1);
        $unitPrice = max(0.0, (float) ($values['unit_price'] ?? 0));
        $discount = max(0.0, min(100.0, (float) ($values['discount_rate'] ?? 0)));
        $values['quantity'] = $quantity > 0 ? $quantity : 1;
        $values['unit_price'] = $unitPrice;
        $values['discount_rate'] = $discount;
        $values['line_total'] = PurchaseQuotationLine::computeTotal($values['quantity'], $unitPrice, $discount);

        foreach (['is_option', 'is_cancelled', 'ai_created_item'] as $flag) {
            if (array_key_exists($flag, $values)) {
                $values[$flag] = (bool) $values[$flag];
            }
        }

        foreach (['brand_model', 'unit', 'note', 'catalog_item_id', 'uom_id'] as $field) {
            if (array_key_exists($field, $values) && blank($values[$field])) {
                $values[$field] = null;
            }
        }

        // D-194: sabit birim kolonu B54 ile gelir.
        if (! UnitResolver::enabled()) {
            unset($values['uom_id']);
        }

        if (array_key_exists('description', $values)) {
            $values['description'] = Str::limit(trim((string) $values['description']), 495, '');
        }

        return $values;
    }

    /** Teklif toplamlari kalemlerden; fiyatli kalem varsa taslak Alindi olur. */
    private function refreshTotals(PurchaseQuotation $quotation): void
    {
        $subtotal = 0.0;
        $lineTotal = 0.0;
        $priced = false;

        foreach ($quotation->lines()->get() as $line) {
            /** @var PurchaseQuotationLine $line */
            if (! $line->counts()) {
                continue;
            }

            $subtotal += $line->grossAmount();
            $lineTotal += (float) $line->getAttribute('line_total');
            $priced = $priced || (float) $line->getAttribute('unit_price') > 0;
        }

        $general = (float) $quotation->getAttribute('general_discount');
        $discount = round(($subtotal - $lineTotal) + $general, 4);
        $values = [
            'subtotal' => round($subtotal, 4),
            'discount_total' => $discount,
            'total_amount' => max(0.0, round($subtotal - $discount, 4)),
        ];

        if ($quotation->statusEnum() === PurchaseQuotationStatus::Draft && $priced) {
            $values['status'] = PurchaseQuotationStatus::Received->value;
        } elseif ($quotation->statusEnum() === PurchaseQuotationStatus::Received && ! $priced) {
            $values['status'] = PurchaseQuotationStatus::Draft->value;
        }

        $quotation->forceFill($values);
        $this->saveWithoutVersion($quotation);
    }

    private function close(PurchaseQuotation $quotation, PurchaseQuotationStatus $to, string $operation, ?string $reason): PurchaseQuotation
    {
        return $this->transactions->run(function () use ($quotation, $to, $operation, $reason): PurchaseQuotation {
            /** @var PurchaseQuotation $locked */
            $locked = $this->lockForUpdate($quotation);
            $this->assertEditable($locked);
            $this->transition($locked, $to, $operation, ['selected_by_personnel_id' => null, 'selected_at' => null], filled($reason) ? ['gerekce' => $reason] : []);
            $this->releaseNeeds($locked, (int) $locked->getKey());

            return $locked;
        });
    }

    /** Teklifin kalemleri baska acik teklif yoksa Siparis bekliyor'a doner. */
    private function releaseNeeds(PurchaseQuotation $quotation, ?int $exceptQuotationId): void
    {
        foreach (PurchaseNeed::query()->whereIn('id', $this->needIdsOf($quotation))->get() as $need) {
            /** @var PurchaseNeed $need */
            $this->needs->revertToQuoting($need, $exceptQuotationId);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $extra
     */
    private function transition(PurchaseQuotation $quotation, PurchaseQuotationStatus $to, string $operation, array $attributes = [], array $extra = []): PurchaseQuotation
    {
        $from = $quotation->statusEnum();
        $quotation->forceFill([...$attributes, 'status' => $to->value]);
        $this->saveWithoutVersion($quotation);
        $this->recordActivity($quotation, $operation, [...$extra, 'durum' => ['onceki' => $from->value, 'yeni' => $to->value]]);

        return $quotation;
    }

    /**
     * @return list<int>
     */
    private function needIdsOf(PurchaseQuotation $quotation): array
    {
        return $quotation->lines()
            ->whereNotNull('purchase_need_id')
            ->where('is_cancelled', false)
            ->pluck('purchase_need_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function lineFromNeed(PurchaseNeed $need): array
    {
        return [
            'purchase_need_id' => $need->getKey(),
            'catalog_item_id' => $need->getAttribute('catalog_item_id'),
            'description' => $need->getAttribute('title'),
            'brand_model' => $need->getAttribute('brand_model'),
            'quantity' => (float) $need->getAttribute('quantity'),
            'unit' => $need->getAttribute('unit'),
            // D-194 (B54): satin alinacak kalemin birimi (createLine kalemin birimini onceler).
            ...(UnitResolver::enabled() ? ['uom_id' => $need->getAttribute('uom_id')] : []),
            'unit_price' => 0,
        ];
    }

    /** Teklife girebilen kalem: proje onayli, acik, siparise girmemis. */
    private function quotableNeed(PurchaseNeed $need): PurchaseNeed
    {
        $quotable = ! $need->isArchived()
            && in_array($need->statusEnum(), [PurchaseNeedStatus::Open, PurchaseNeedStatus::Quoting, PurchaseNeedStatus::Recommended], true)
            && ! $need->orderLines()->exists();

        if (! $quotable) {
            throw PurchaseNeedNotQuotableException::make(['title' => Str::limit((string) $need->getAttribute('title'), 80)]);
        }

        return $need;
    }

    private function assertEditable(PurchaseQuotation $quotation): void
    {
        if ($quotation->isArchived() || ! $quotation->statusEnum()->isEditable()) {
            throw PurchaseQuotationLockedException::make();
        }
    }
}
