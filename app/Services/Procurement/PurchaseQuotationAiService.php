<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Document\FileObjectStatus;
use App\Enums\Document\FileScanStatus;
use App\Enums\Platform\Feature;
use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\CatalogMatchStatus;
use App\Enums\Procurement\QuotationAiStatus;
use App\Enums\Procurement\QuotationDocumentAiStatus;
use App\Exceptions\AbstractException;
use App\Exceptions\AiService\AiServiceAccessDeniedException;
use App\Exceptions\AiService\AiServiceUnavailableException;
use App\Exceptions\Procurement\PurchaseQuotationLockedException;
use App\Exceptions\Procurement\QuotationAiBusyException;
use App\Exceptions\Procurement\QuotationAiNoReadableDocumentException;
use App\Exceptions\Procurement\QuotationLineCatalogRequiredException;
use App\Http\Middleware\Infrastructure\Documents\OfficeTextExtractor;
use App\Infrastructure\AiService\AiServiceClient;
use App\Models\Document\FileObject;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationDocument;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Query\Procurement\QuotationAiQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Procurement\UnitResolver;
use BackedEnum;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Konelsis AI ile satin alma teklifi okuma (D-192, calisma alani
 * u-mv0njps9-98cc; ai-service planlama kaydi 29, capability
 * `procurement.quotation_extraction`). Kullanici: "Birden fazla belge de
 * yukleyebilir. Teklif belgesi mi diye kontrol edecek. Eger teklif belgesi ise
 * bilgileri dolduracak ... sistemde urun varsa direk o ayni urunu ... yoksa yeni
 * urun kaydini arka planda olusturacak".
 *
 * - read(): belgeler Dokuman yonetimine eklenir (AI bekliyor) ve submit() ile
 *   tek istekte ai-service'e gider. PDF ve fotograf base64, Word / Excel
 *   duz metin (OfficeTextExtractor); eski .doc / .xls ve diger turler o an
 *   "Okunamadi" olur. Kuyruk iscisi gerekmez: istek senkron gonderilir, sonuc
 *   teklif sayfasindaki QuotationAiProgress bileseniyle yoklanir (poll()).
 * - poll(): sonuc gelince her belgeye teklif mi / degil / okunamadi ve kisa
 *   Turkce ozet yazilir; teklif belgelerinden baslik (yalniz bos alanlar,
 *   PurchaseQuotationService::applyExtraction) ve kalemler yazilir. Kalem
 *   Malzeme ve Hizmetler'de varsa (ad, esleme adi, cok benzer ad) o kaleme
 *   baglanir; yoksa arka planda acilir ve satir `ai_created_item` olur. Bu
 *   yazimlar "Sistem" adina yapilir (AI servis hesabi; kisi bagi yok).
 * - reviewItems(): "Konelsis AI olarak yeni olusturdugum urunler" penceresi.
 *   Kabul edilen satirin `ai_created_item` isareti kaldirilir (yeni kolon yok:
 *   isaretli satir = gozden gecirilmemis AI kalemi). "Listeden baska kalem sec"
 *   satiri secilen kaleme baglar, AI'nin yazimini o kaleme esleme adi yapar
 *   (D-187) ve baska yerde kullanilmayan AI kalemini kaldirir.
 */
final class PurchaseQuotationAiService extends AbstractService
{
    public const CAPABILITY = 'procurement.quotation_extraction';

    public const RESULT_SCHEMA = 'procurement.quotation.v1';

    /** ai-service kayit 29 sinirlari: istek basina belge, belge / toplam bayt, metin. */
    public const MAX_DOCUMENTS = 10;

    public const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;

    public const MAX_TOTAL_BYTES = 20 * 1024 * 1024;

    private const MAX_TEXT_CHARACTERS = 200000;

    /** Word / Excel'den metin cikarilacak en buyuk dosya. */
    private const MAX_OFFICE_BYTES = 25 * 1024 * 1024;

    /** @var array<string, string> */
    private const BINARY_TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    protected string $model = PurchaseQuotation::class;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly PurchaseQuotationService $quotations,
        private readonly CatalogItemService $catalog,
        private readonly QuotationAiQueries $queries,
        private readonly AiServiceClient $client,
        private readonly OfficeTextExtractor $office,
        private readonly UnitResolver $units,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /** Ozellik acik, B53 uygulanmis ve ai-service baglantisi tanimli mi? */
    public function available(): bool
    {
        return SchemaReadiness::hasBatch('B53')
            && FeatureFlags::enabled(Feature::PurchaseQuotations)
            && FeatureFlags::enabled(Feature::PurchaseQuotationsAi)
            && $this->client->isConfigured();
    }

    /** Okuma suruyor mu (ve suresi asilmamis mi)? */
    public function isReading(PurchaseQuotation $quotation): bool
    {
        return $quotation->aiStatusEnum() === QuotationAiStatus::Pending && ! $this->isStale($quotation);
    }

    /**
     * Belgeleri ekler ve Konelsis AI'ye gonderir; gonderilen belge sayisini doner.
     *
     * @param  list<array{path: string, name: string|null}>  $files
     */
    public function read(PurchaseQuotation $quotation, array $files): int
    {
        $this->assertCanStart($quotation);
        $this->quotations->attachDocuments($quotation, $files, true);

        return $this->submit($quotation->refresh());
    }

    /** "AI bekliyor" belgeleri tek istekte gonderir. */
    public function submit(PurchaseQuotation $quotation): int
    {
        $this->assertCanStart($quotation);

        $rows = [];
        $documents = [];
        $total = 0;

        foreach ($this->queries->pendingDocuments($quotation) as $row) {
            /** @var PurchaseQuotationDocument $row */
            if (count($documents) >= self::MAX_DOCUMENTS) {
                $this->failDocument($row, 'too_many');

                continue;
            }

            $prepared = $this->prepareDocument($row, $total);

            if (is_string($prepared)) {
                $this->failDocument($row, $prepared);

                continue;
            }

            $total += $prepared['size'];
            $rows[] = $row;
            $documents[] = [
                'index' => count($documents) + 1,
                'reference' => (int) $row->getKey(),
                'version' => (string) $row->getAttribute('document_revision_id'),
                'file_name' => $prepared['name'],
                'mime_type' => $prepared['mime'],
                'hash' => hash('sha256', $prepared['content']),
                'content_base64' => $prepared['mime'] === 'text/plain' ? null : base64_encode($prepared['content']),
                'text' => $prepared['mime'] === 'text/plain' ? $prepared['content'] : null,
            ];
        }

        if ($documents === []) {
            $this->quotations->setAiStatus($quotation, QuotationAiStatus::Failed);

            throw QuotationAiNoReadableDocumentException::make();
        }

        $requestedBy = $this->actor->personnelId() ?? throw AiServiceAccessDeniedException::make();
        $requestId = $this->newRequestId();
        $body = [
            'schema_version' => '1.0',
            'request_id' => $requestId,
            'capability_code' => self::CAPABILITY,
            'locale' => app()->getLocale() === 'en' ? 'en' : 'tr',
            'requested_by_user_id' => $requestedBy,
            'subject_user_id' => null,
            'subject_type' => 'purchase_quotation',
            'subject_id' => (int) $quotation->getKey(),
            'subject_revision' => Str::limit('docs:'.implode(',', array_map(static fn (array $document): string => $document['version'], $documents)), 191, ''),
            'source_hash' => $this->sourceHash($documents),
            'documents' => $documents,
        ];

        try {
            $this->client->submitAnalysis($body, 'crm-pq-'.$quotation->getKey().'-'.$requestId);
        } catch (AbstractException $exception) {
            foreach ($rows as $row) {
                $this->failDocument($row, 'not_sent');
            }

            $this->quotations->setAiStatus($quotation, QuotationAiStatus::Failed);

            throw $exception;
        }

        $this->quotations->setAiStatus($quotation, QuotationAiStatus::Pending, (string) $requestId);

        return count($documents);
    }

    /**
     * Bekleyen okumanin sonucunu alir ve yazar. Okuma surerken durum Pending
     * kalir; sayfa bileseni birkac saniyede bir cagirir.
     *
     * @return array{status: QuotationAiStatus|null, message: string|null, new_items: int, quotations: int}
     */
    public function poll(PurchaseQuotation $quotation): array
    {
        if ($quotation->aiStatusEnum() !== QuotationAiStatus::Pending) {
            return ['status' => $quotation->aiStatusEnum(), 'message' => null, 'new_items' => 0, 'quotations' => 0];
        }

        $requestId = (int) $quotation->getAttribute('ai_job_reference');

        if ($requestId <= 0) {
            return $this->finishFailed($quotation, 'service');
        }

        try {
            $status = $this->client->analysisStatus($requestId);
            $state = (string) ($status['status'] ?? '');

            if ($state === 'failed') {
                return $this->finishFailed($quotation, ($status['safe_error_code'] ?? '') === 'AGENT_LIMIT_EXHAUSTED' ? 'limit' : 'service');
            }

            $result = $state === 'completed' ? $this->client->analysisResult($requestId) : null;
        } catch (AiServiceUnavailableException) {
            return $this->isStale($quotation) ? $this->finishFailed($quotation, 'timeout') : $this->pending();
        } catch (AbstractException) {
            return $this->finishFailed($quotation, 'service');
        }

        if ($result === null) {
            return $this->isStale($quotation) ? $this->finishFailed($quotation, 'timeout') : $this->pending();
        }

        $extraction = $result['quotation_extraction'] ?? null;

        if (! is_array($extraction) || ($extraction['schema_version'] ?? null) !== self::RESULT_SCHEMA || ! is_array($extraction['documents'] ?? null)) {
            return $this->finishFailed($quotation, 'invalid_result');
        }

        try {
            return $this->apply($quotation, $extraction['documents']);
        } catch (Throwable) {
            return $this->finishFailed($quotation, 'service');
        }
    }

    /**
     * "Konelsis AI olarak yeni olusturdugum urunler" penceresinin kararlari.
     *
     * @param  list<array<string, mixed>>  $decisions  line_id, decision (accept|edit|relink|null), name, kind,
     *                                                 catalog_category_id, unit, brand, catalog_item_id
     * @return array{accepted: int, edited: int, relinked: int}
     */
    public function reviewItems(PurchaseQuotation $quotation, array $decisions): array
    {
        return $this->transactions->run(function () use ($quotation, $decisions): array {
            /** @var PurchaseQuotation $locked */
            $locked = $this->lockForUpdate($quotation);

            if ($locked->isArchived() || ! $locked->statusEnum()->isEditable()) {
                throw PurchaseQuotationLockedException::make();
            }

            $lines = $this->queries->aiCreatedLines($locked)->keyBy(static fn (PurchaseQuotationLine $line): int => (int) $line->getKey());
            $counts = ['accepted' => 0, 'edited' => 0, 'relinked' => 0];

            foreach ($decisions as $decision) {
                $line = $lines->get((int) ($decision['line_id'] ?? 0));
                $choice = $decision['decision'] ?? null;
                $choice = $choice instanceof BackedEnum ? (string) $choice->value : (is_string($choice) ? $choice : null);

                if (! $line instanceof PurchaseQuotationLine || ! in_array($choice, ['accept', 'edit', 'relink'], true)) {
                    continue;
                }

                $item = $line->catalogItem;
                // D-194 (B54): birim sabit listeden (`uom_id`); oncesinde serbest metin.
                $unitKey = UnitResolver::enabled() ? 'uom_id' : 'unit';

                if ($choice === 'edit' && $item instanceof CatalogItem) {
                    $this->catalog->update($item, array_filter([
                        'name' => filled($decision['name'] ?? null) ? (string) $decision['name'] : null,
                        'kind' => filled($decision['kind'] ?? null) ? $this->value($decision['kind']) : null,
                        'catalog_category_id' => filled($decision['catalog_category_id'] ?? null) ? (int) $decision['catalog_category_id'] : null,
                        $unitKey => $decision[$unitKey] ?? null,
                        'brand' => $decision['brand'] ?? null,
                    ], static fn (mixed $value, string $key): bool => $value !== null || in_array($key, [$unitKey, 'brand'], true), ARRAY_FILTER_USE_BOTH));
                }

                // D-194: "Urun olusurken birimi belirlenmelidir" - kabul edilen birimsiz AI kalemine secilen birim yazilir.
                if ($choice === 'accept' && $item instanceof CatalogItem && UnitResolver::enabled()
                    && $item->getAttribute('uom_id') === null && filled($decision['uom_id'] ?? null)) {
                    $this->catalog->update($item, ['uom_id' => $decision['uom_id']]);
                }

                if ($choice === 'relink') {
                    $target = $this->queries->activeItem((int) ($decision['catalog_item_id'] ?? 0))
                        ?? throw QuotationLineCatalogRequiredException::make(['line' => Str::limit((string) $line->getAttribute('description'), 80)]);

                    if (! $item instanceof CatalogItem || (int) $target->getKey() !== (int) $item->getKey()) {
                        // Satir once yeni kaleme baglanir; eski AI kalemi ancak sonra kaldirilabilir.
                        $line->forceFill(['catalog_item_id' => $target->getKey(), 'ai_created_item' => false]);
                        $this->saveWithoutVersion($line);
                        $this->relinkCleanup($target, $item, (int) $line->getKey());
                    }
                }

                $line->forceFill(['ai_created_item' => false]);

                // D-194 (B54): satirin birimi bagli kalemin (duzenlenmis / yeni) birimini izler.
                if (UnitResolver::enabled()) {
                    $line->forceFill($this->units->apply(
                        ['uom_id' => $line->getAttribute('uom_id'), 'unit' => $line->getAttribute('unit')],
                        $line->catalogItem()->first(),
                    ));
                }

                $this->saveWithoutVersion($line);
                $counts[match ($choice) {
                    'accept' => 'accepted',
                    'edit' => 'edited',
                    default => 'relinked',
                }]++;
            }

            if (array_sum($counts) > 0) {
                $this->recordActivity($locked, 'updated', ['konelsis_ai_kalemleri' => [
                    'kabul' => $counts['accepted'],
                    'duzenlenen' => $counts['edited'],
                    'baska_kaleme_baglanan' => $counts['relinked'],
                ]]);
            }

            return $counts;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $documents  ai-service `quotation_extraction.documents`
     * @return array{status: QuotationAiStatus|null, message: string|null, new_items: int, quotations: int}
     */
    private function apply(PurchaseQuotation $quotation, array $documents): array
    {
        // AI'nin yazdiklari kisiye degil "Sistem"e yazilir (AI servis hesabi).
        return $this->actor->runAsSystem(fn (): array => $this->transactions->run(function () use ($quotation, $documents): array {
            /** @var PurchaseQuotation $locked */
            $locked = $this->lockForUpdate($quotation);

            // Ayni sonucu baska bir sekme yazdiysa tekrar yazilmaz.
            if ($locked->aiStatusEnum() !== QuotationAiStatus::Pending) {
                return ['status' => $locked->aiStatusEnum(), 'message' => null, 'new_items' => 0, 'quotations' => 0];
            }

            $rows = $this->queries->pendingDocuments($locked)->keyBy(static fn (PurchaseQuotationDocument $row): int => (int) $row->getKey());
            $editable = ! $locked->isArchived() && $locked->statusEnum()->isEditable();
            $header = [];
            $lines = [];
            $quotationRows = [];

            foreach ($documents as $document) {
                $row = is_array($document) ? $rows->get((int) ($document['reference'] ?? 0)) : null;

                if (! $row instanceof PurchaseQuotationDocument) {
                    continue;
                }

                $rows->forget((int) $row->getKey());
                $reason = trim((string) ($document['reason'] ?? ''));

                if (($document['document_kind'] ?? null) === 'unreadable') {
                    $this->quotations->setDocumentAiResult($row, QuotationDocumentAiStatus::Failed, $reason !== '' ? $reason : __('purchase_quotation_ai.document.unreadable'));

                    continue;
                }

                if (($document['is_quotation'] ?? false) !== true) {
                    $this->quotations->setDocumentAiResult($row, QuotationDocumentAiStatus::NotQuotation, $reason);

                    continue;
                }

                $quotationRows[] = [$row, $reason, count((array) ($document['lines'] ?? []))];
                $this->collectHeader($header, $document, $locked);

                foreach ((array) ($document['lines'] ?? []) as $line) {
                    if (is_array($line)) {
                        $lines[] = $line;
                    }
                }
            }

            foreach ($rows as $row) {
                $this->failDocument($row, 'no_result');
            }

            $notes = [];
            $applied = ['filled_fields' => [], 'priced' => 0, 'added' => 0];
            $newItems = 0;

            if ($quotationRows !== [] && ! $editable) {
                $notes[] = __('purchase_quotation_ai.document.locked');
            } elseif ($quotationRows !== []) {
                $currency = $this->settleCurrency($locked, $header['_currency'] ?? null, $notes);
                unset($header['_currency']);
                [$prepared, $newItems] = $this->catalogLines($lines, $currency);
                $applied = $this->quotations->applyExtraction($locked, $header, $prepared);
            }

            foreach ($quotationRows as [$row, $reason, $lineCount]) {
                $this->quotations->setDocumentAiResult($row, QuotationDocumentAiStatus::Quotation, trim(implode(' ', [
                    __('purchase_quotation_ai.document.quotation_summary', ['reason' => $reason, 'lines' => $lineCount]),
                    ...$notes,
                ])));
            }

            $this->quotations->setAiStatus($locked, QuotationAiStatus::Completed);

            return [
                'status' => QuotationAiStatus::Completed,
                'message' => $quotationRows === []
                    ? __('purchase_quotation_ai.notifications.no_quotation_body')
                    : __('purchase_quotation_ai.notifications.completed_body', [
                        'documents' => count($quotationRows),
                        'filled' => count($applied['filled_fields']),
                        'priced' => $applied['priced'],
                        'added' => $applied['added'],
                    ]),
                'new_items' => $newItems,
                'quotations' => count($quotationRows),
            ];
        }));
    }

    /**
     * Baslik alanlari: ilk teklif belgesinin dolu degeri. Tedarikci teklifte bos
     * ise once vergi numarasi, sonra ad ile taraf aranir; bulunmazsa serbest ad.
     *
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $document
     */
    private function collectHeader(array &$header, array $document, PurchaseQuotation $quotation): void
    {
        $supplier = is_array($document['supplier'] ?? null) ? $document['supplier'] : [];
        $data = is_array($document['quotation'] ?? null) ? $document['quotation'] : [];
        $put = static function (string $field, mixed $value) use (&$header): void {
            if (! array_key_exists($field, $header) && filled($value)) {
                $header[$field] = $value;
            }
        };

        if (! isset($header['supplier_party_id']) && ! isset($header['supplier_name'])
            && blank($quotation->getAttribute('supplier_party_id')) && blank($quotation->getAttribute('supplier_name'))) {
            $partyId = $this->queries->supplierPartyId($supplier['tax_no'] ?? null, $supplier['name'] ?? null);

            if ($partyId !== null) {
                $header['supplier_party_id'] = $partyId;
            } elseif (filled($supplier['name'] ?? null)) {
                $header['supplier_name'] = Str::limit(trim((string) $supplier['name']), 200, '');
            }
        }

        $put('supplier_reference', filled($data['reference'] ?? null) ? Str::limit((string) $data['reference'], 100, '') : null);
        $put('quote_date', $data['date'] ?? null);
        $put('valid_until', $data['valid_until'] ?? null);
        $put('delivery_on', $data['delivery_date'] ?? null);
        $put('payment_terms', filled($data['payment_terms'] ?? null) ? Str::limit((string) $data['payment_terms'], 255, '') : null);
        $put('delivery_terms', filled($data['delivery_terms'] ?? null) ? Str::limit((string) $data['delivery_terms'], 255, '') : null);
        $put('lead_time_days', is_int($data['lead_time_days'] ?? null) ? $data['lead_time_days'] : null);
        $put('general_discount', is_numeric($data['general_discount'] ?? null) && (float) $data['general_discount'] > 0 ? (float) $data['general_discount'] : null);
        $put('note', filled($data['notes'] ?? null) ? Str::limit((string) $data['notes'], 2000, '') : null);
        $put('_currency', filled($data['currency'] ?? null) ? strtoupper((string) $data['currency']) : null);
    }

    /**
     * Belgenin para birimi teklifinkinden farkliysa ve teklifte henuz fiyat
     * girilmemisse teklifin para birimi belgeninki olur (varsayilan TL ile
     * dovizli fiyatlar yazilmasin); fiyatli kalem varsa kullanicinin secimi kalir.
     *
     * @param  list<string>  $notes
     */
    private function settleCurrency(PurchaseQuotation $quotation, ?string $documentCurrency, array &$notes): string
    {
        $current = strtoupper((string) ($quotation->getAttribute('currency_code') ?: 'TRY'));

        if ($documentCurrency === null || $documentCurrency === $current || ! $this->queries->currencyExists($documentCurrency)) {
            return $current;
        }

        if ($this->queries->hasPricedLines($quotation)) {
            $notes[] = __('purchase_quotation_ai.document.currency_kept', ['document' => $documentCurrency, 'current' => $current]);

            return $current;
        }

        $this->quotations->update($quotation, ['currency_code' => $documentCurrency]);
        $quotation->refresh();

        return $documentCurrency;
    }

    /**
     * Kalemleri Malzeme ve Hizmetler'le eslestirir; listede olmayani acar.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function catalogLines(array $lines, string $currency): array
    {
        $prepared = [];
        $created = [];

        foreach ($lines as $line) {
            $description = trim((string) ($line['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $kind = ($line['item_kind'] ?? null) === 'service' ? CatalogItemKind::Service : CatalogItemKind::Product;
            $unit = filled($line['unit'] ?? null) ? Str::limit(trim((string) $line['unit']), 40, '') : null;
            // D-194 (B54): AI'nin birim metni sabit listeden cozulur (taninmazsa bos, kalem gozden gecirilirken secilir).
            $uomId = UnitResolver::enabled() ? $this->units->resolve($unit) : null;
            $brand = filled($line['brand_model'] ?? null) ? trim((string) $line['brand_model']) : null;
            $item = null;
            $isNew = false;

            try {
                $match = $this->catalog->match(Str::limit($description, 300, ''), $kind, ['unit' => $unit, 'uom_id' => $uomId, 'currency' => $currency]);
                // Cok benzer ad ayni urun sayilir: katalogda cift kayit acilmaz (D-187).
                $item = $match['item'] ?? $match['suggested'] ?? null;
                $isNew = $match['status'] === CatalogMatchStatus::New && $item instanceof CatalogItem;

                if ($isNew && $brand !== null && blank($item->getAttribute('brand'))) {
                    $this->catalog->update($item, ['brand' => Str::limit($brand, 120, '')]);
                }
            } catch (Throwable) {
                // Ornegin arsivdeki ayni adli kalem: satir katalogsuz eklenir, elle baglanir.
                $item = null;
                $isNew = false;
            }

            if ($isNew) {
                $created[(int) $item->getKey()] = true;
            }

            $quantity = is_numeric($line['quantity'] ?? null) && (float) $line['quantity'] > 0 ? (float) $line['quantity'] : 1.0;
            $unitPrice = is_numeric($line['unit_price'] ?? null) ? (float) $line['unit_price'] : null;
            $discount = is_numeric($line['discount_rate'] ?? null) ? (float) $line['discount_rate'] : null;

            if ($unitPrice === null && is_numeric($line['line_total'] ?? null)) {
                // Yalniz satir tutari varsa net birim fiyat (indirim zaten icinde).
                $unitPrice = round((float) $line['line_total'] / $quantity, 6);
                $discount = null;
            }

            $prepared[] = [
                'catalog_item_id' => $item instanceof CatalogItem ? (int) $item->getKey() : null,
                'description' => $description,
                'brand_model' => $brand !== null ? Str::limit($brand, 200, '') : null,
                'quantity' => $quantity,
                'unit' => $unit,
                ...(UnitResolver::enabled() ? ['uom_id' => $uomId] : []),
                'unit_price' => $unitPrice ?? 0,
                'discount_rate' => $discount ?? 0,
                'is_option' => ($line['is_option'] ?? false) === true,
                'ai_created_item' => $isNew,
            ];
        }

        return [$prepared, count($created)];
    }

    /**
     * Gonderilecek icerik ya da okunamama nedeni (dil anahtari).
     *
     * @return array{name: string, mime: string, content: string, size: int}|string
     */
    private function prepareDocument(PurchaseQuotationDocument $row, int $total): array|string
    {
        $file = $row->revision?->originalFile();

        if (! $file instanceof FileObject) {
            return 'missing';
        }

        $status = $file->getAttribute('status');
        $scan = $file->getAttribute('scan_status');

        if (($status instanceof FileObjectStatus && $status !== FileObjectStatus::Active)
            || in_array($scan, [FileScanStatus::Infected, FileScanStatus::Quarantined], true)) {
            return 'blocked';
        }

        $name = Str::limit(trim((string) $file->getAttribute('original_name')) ?: 'belge', 255, '');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: (string) $file->getAttribute('extension'));
        $bytes = (int) $file->getAttribute('byte_size');

        if (in_array($extension, ['doc', 'xls'], true)) {
            return 'legacy_format';
        }

        if (isset(self::BINARY_TYPES[$extension])) {
            if ($bytes > self::MAX_DOCUMENT_BYTES || $total + $bytes > self::MAX_TOTAL_BYTES) {
                return 'too_large';
            }

            $content = $this->contents($file);

            if ($content === null) {
                return 'unreadable';
            }

            $mime = $this->sniff($content);

            if ($mime === null) {
                return 'unreadable';
            }

            return ['name' => $name, 'mime' => $mime, 'content' => $content, 'size' => strlen($content)];
        }

        if (OfficeTextExtractor::supports($extension)) {
            if ($bytes > self::MAX_OFFICE_BYTES) {
                return 'too_large';
            }

            $text = $this->officeText($file, $extension);

            if ($text === null) {
                return 'unreadable';
            }

            if ($text === '') {
                return 'empty';
            }

            if ($total + strlen($text) > self::MAX_TOTAL_BYTES) {
                return 'too_large';
            }

            return ['name' => $name, 'mime' => 'text/plain', 'content' => $text, 'size' => strlen($text)];
        }

        return 'unsupported';
    }

    /** Ilk baytlara gore gercek tur; bildirilen uzantiya guvenilmez. */
    private function sniff(string $content): ?string
    {
        return match (true) {
            str_starts_with($content, '%PDF-') => 'application/pdf',
            str_starts_with($content, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($content, "\x89PNG\r\n\x1A\n") => 'image/png',
            strlen($content) > 12 && str_starts_with($content, 'RIFF') && substr($content, 8, 4) === 'WEBP' => 'image/webp',
            default => null,
        };
    }

    private function contents(FileObject $file): ?string
    {
        try {
            $content = $this->disk($file)->get((string) $file->getAttribute('storage_key'));
        } catch (Throwable) {
            return null;
        }

        return is_string($content) && $content !== '' ? $content : null;
    }

    private function officeText(FileObject $file, string $extension): ?string
    {
        $disk = $this->disk($file);
        $key = (string) $file->getAttribute('storage_key');
        $temp = null;

        try {
            $path = $disk instanceof FilesystemAdapter ? $disk->path($key) : null;

            if ($path === null || ! is_file($path)) {
                // Yerel olmayan disk: gecici kopya.
                $temp = tempnam(sys_get_temp_dir(), 'kcai');
                $content = $disk->get($key);

                if ($temp === false || ! is_string($content) || file_put_contents($temp, $content) === false) {
                    return null;
                }

                $path = $temp;
            }

            return $this->office->extract($path, $extension, self::MAX_TEXT_CHARACTERS);
        } catch (Throwable) {
            return null;
        } finally {
            if (is_string($temp) && is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    private function disk(FileObject $file): Filesystem
    {
        return Storage::disk((string) ($file->getAttribute('storage_disk') ?: 'local'));
    }

    /**
     * ai-service kayit 29: {"documents":[{"hash","index","reference","version"}]}
     * nesnesinin RFC 8785 kanonik JSON'unun SHA-256'si (anahtarlar zaten sirali).
     *
     * @param  list<array<string, mixed>>  $documents
     */
    private function sourceHash(array $documents): string
    {
        $references = array_map(static fn (array $document): array => [
            'hash' => $document['hash'],
            'index' => $document['index'],
            'reference' => $document['reference'],
            'version' => $document['version'],
        ], $documents);

        return hash('sha256', (string) json_encode(['documents' => $references], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Istemci kapsaminda tekil, pozitif istek kimligi (zaman + rastgele). */
    private function newRequestId(): int
    {
        return (int) Carbon::now('UTC')->getTimestampMs() * 100 + random_int(0, 99);
    }

    private function assertCanStart(PurchaseQuotation $quotation): void
    {
        if ($quotation->isArchived() || ! $quotation->statusEnum()->isEditable()) {
            throw PurchaseQuotationLockedException::make();
        }

        if ($this->isReading($quotation)) {
            throw QuotationAiBusyException::make();
        }
    }

    private function isStale(PurchaseQuotation $quotation): bool
    {
        $requested = $quotation->getAttribute('ai_requested_at');
        $minutes = max(1, (int) config('services.ai_service.stale_after_minutes', 20));

        return $requested === null || $requested->lt(Carbon::now('UTC')->subMinutes($minutes));
    }

    /**
     * @return array{status: QuotationAiStatus|null, message: string|null, new_items: int, quotations: int}
     */
    private function finishFailed(PurchaseQuotation $quotation, string $reason): array
    {
        $this->transactions->run(function () use ($quotation, $reason): void {
            /** @var PurchaseQuotation $locked */
            $locked = $this->lockForUpdate($quotation);

            if ($locked->aiStatusEnum() !== QuotationAiStatus::Pending) {
                return;
            }

            foreach ($this->queries->pendingDocuments($locked) as $row) {
                $this->quotations->setDocumentAiResult($row, QuotationDocumentAiStatus::Failed, __('purchase_quotation_ai.failure.'.$reason));
            }

            $this->quotations->setAiStatus($locked, QuotationAiStatus::Failed);
        });

        return ['status' => QuotationAiStatus::Failed, 'message' => __('purchase_quotation_ai.failure.'.$reason), 'new_items' => 0, 'quotations' => 0];
    }

    /**
     * @return array{status: QuotationAiStatus, message: null, new_items: int, quotations: int}
     */
    private function pending(): array
    {
        return ['status' => QuotationAiStatus::Pending, 'message' => null, 'new_items' => 0, 'quotations' => 0];
    }

    private function failDocument(PurchaseQuotationDocument $row, string $reason): void
    {
        $this->quotations->setDocumentAiResult($row, QuotationDocumentAiStatus::Failed, __('purchase_quotation_ai.document.'.$reason));
    }

    /**
     * Satir baska kaleme baglaninca: AI'nin yazimi secilen kalemin esleme adi olur
     * (bir dahaki okuma ve Excel aktarimi oraya baglanir); AI kalemi baska yerde
     * kullanilmiyorsa kaldirilir.
     */
    private function relinkCleanup(CatalogItem $target, ?CatalogItem $aiItem, int $lineId): void
    {
        if (! $aiItem instanceof CatalogItem) {
            return;
        }

        try {
            $this->catalog->link($target, (string) $aiItem->getAttribute('name'));
        } catch (Throwable) {
            // Esleme adi zaten baska bir kaleme bagliysa dokunulmaz (kayit noktasi geri alindi).
        }

        if (! $this->queries->itemUsedElsewhere((int) $aiItem->getKey(), $lineId)) {
            $this->catalog->dropUnused((int) $aiItem->getKey());
        }
    }

    private function value(mixed $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (string) $value;
    }
}
