<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Approval\ApprovalRequestStatus;
use App\Enums\Procurement\CatalogItemKind;
use App\Enums\Procurement\CatalogMatchStatus;
use App\Enums\Procurement\UsageArea;
use App\Exceptions\Procurement\CatalogItemRequiredException;
use App\Exceptions\Procurement\PurchaseRequestIncompleteException;
use App\Services\Platform\SchemaReadiness;
use App\Enums\Procurement\PurchaseNeedSource;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseQuotationStatus;
use App\Enums\Procurement\PurchaseRequestStatus;
use App\Models\Procurement\PurchaseRequest;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Procurement\CostLineNotMatchedException;
use App\Exceptions\Procurement\ProjectApprovalPendingException;
use App\Exceptions\Procurement\PurchaseApprovalRequiredException;
use App\Exceptions\Procurement\PurchaseNeedClosedException;
use App\Exceptions\Procurement\PurchaseQuoteRateRequiredException;
use App\Exceptions\Procurement\PurchaseQuoteRequiredException;
use App\Exceptions\RecordNotFoundException;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Approval\ApprovalRequest;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\CostList;
use App\Models\Procurement\CostListLine;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseQuote;
use App\Models\Project\Project;
use App\Services\AbstractService;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Approval\Subjects\PurchaseNeedSubject;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\DisplayTime;
use App\Support\Procurement\UnitResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Siparis kalemleri (B52, D-189). Kullanicinin kurallari (10 Ekim 2026):
 *
 * - Teklif maliyet listesinden gelen kalem "bir Teklif araciligi ile geldigi
 *   bilinmelidir": teklif (ve varsa projesi) kaleme yazilir.
 * - "Proje onay vermeden satin alima dusmeyecek": teklif grubunun gonderdigi
 *   kalem Proje onayi bekliyor durumunda acilir; proje onaylayinca Siparis
 *   bekliyor olur. Departman talebi (sirket ici) ve satin almanin elle actigi
 *   kalem dogrudan Siparis bekliyor'dur.
 * - Birden fazla tedarikci teklifi (PurchaseQuoteService); satin alma en uygun
 *   teklifi onerilen yapar (`recommend`).
 * - Onay (PurchaseApprovals): katalogda "Onay gerekir" isaretli kalemde
 *   onerilen teklifin TL tutari 4.000 TL'ye kadar Satin alma mudurune, ustu
 *   Yonetim kurulu baskanina gider. Onay olmadan siparis verilemez ve
 *   tamamlanamaz (PurchaseApprovalRequiredException; ekran merci listesini
 *   uyari penceresinde gosterir).
 * - Tamamlanan departman talebi / sirket ici kalem Sirket ici alimlar'a
 *   kendiliginden yazilir (yenileme donemiyle).
 * - Silme yok; iptal ve arsiv var (D-156).
 */
final class PurchaseNeedService extends AbstractService
{
    protected string $orderBy = 'needed_by';

    /** @var list<string> Formdan degisebilen alanlar. */
    private const EDITABLE = [
        'title', 'quantity', 'unit', 'needed_by', 'is_urgent', 'note', 'org_unit_id', 'project_id',
        // D-190 satin alma talebi
        'usage_area', 'brand_model', 'specification', 'justification', 'existing_check',
        // D-194 (B54): sabit birim (UnitResolver::apply; B54 oncesinde yazilmaz)
        'uom_id',
    ];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly PurchaseApprovals $approvals,
        private readonly ApprovalRequestService $approvalRequests,
        private readonly CompanyPurchaseService $companyPurchases,
        private readonly CatalogItemService $catalog,
        private readonly UnitResolver $units,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Teklif grubunun sectigi maliyet kalemlerini satin almaya gonderir. Tutari
     * olmayan, katalogla eslesmemis ya da daha once gonderilmis kalem atlanir.
     *
     * @param  iterable<CostListLine>  $lines
     * @param  array{needed_by?: string|null, is_urgent?: bool, note?: string|null}  $options
     * @return array{created: int, unmatched: int, skipped: int}
     */
    public function sendCostLines(iterable $lines, array $options = []): array
    {
        return $this->transactions->run(function () use ($lines, $options): array {
            $result = ['created' => 0, 'unmatched' => 0, 'skipped' => 0];
            $actor = $this->actorModel();

            foreach ($lines as $line) {
                if (! $line instanceof CostListLine) {
                    continue;
                }

                if (! $line->hasCost() || $line->purchaseNeed()->exists()) {
                    $result['skipped']++;

                    continue;
                }

                if ($line->getAttribute('catalog_item_id') === null) {
                    $result['unmatched']++;

                    continue;
                }

                // Idari kadro gibi satin alinmayan kalemler satin almaya gitmez.
                $item = $line->catalogItem;

                if ($item instanceof CatalogItem && ! $item->kindEnum()->isPurchasable()) {
                    $result['skipped']++;

                    continue;
                }

                /** @var CostList $list */
                $list = $line->costList()->with('proposal.businessCase')->firstOrFail();
                $proposal = $list->proposal;
                $quantity = (float) $line->getAttribute('quantity');

                parent::create([
                    'source' => PurchaseNeedSource::ProposalCost->value,
                    // Saha maliyet listesinden gelir (D-190).
                    'usage_area' => SchemaReadiness::hasBatch('B52A') ? UsageArea::Field->value : null,
                    'catalog_item_id' => $line->getAttribute('catalog_item_id'),
                    'cost_list_line_id' => $line->getKey(),
                    'proposal_id' => $list->getAttribute('proposal_id'),
                    'project_id' => $this->projectIdOf($proposal),
                    'org_unit_id' => $actor?->getAttribute('org_unit_id'),
                    'requested_by_personnel_id' => $actor?->getKey(),
                    'title' => Str::limit((string) $line->getAttribute('description'), 495, ''),
                    'quantity' => $quantity > 0 ? $quantity : 1,
                    // D-194 (B54): katalog kaleminin birimi; yoksa maliyet satirinin metninden.
                    ...$this->units->apply(['unit' => $line->getAttribute('unit')], $item instanceof CatalogItem ? $item : null),
                    'estimated_unit_cost' => $line->getAttribute('unit_cost'),
                    'estimated_currency_code' => $line->getAttribute('currency_code'),
                    'needed_by' => $options['needed_by'] ?? null,
                    'is_urgent' => (bool) ($options['is_urgent'] ?? false),
                    'status' => PurchaseNeedStatus::AwaitingProject->value,
                    'note' => $options['note'] ?? null,
                ]);
                $result['created']++;
            }

            return $result;
        });
    }

    /** Tek bir maliyet kalemini gonderir; eslesmemisse is hatasi verir. */
    public function sendCostLine(CostListLine $line, array $options = []): PurchaseNeed
    {
        if ($line->getAttribute('catalog_item_id') === null) {
            throw CostLineNotMatchedException::make(['line' => Str::limit((string) $line->getAttribute('description'), 80)]);
        }

        $this->sendCostLines([$line], $options);

        /** @var PurchaseNeed $need */
        $need = PurchaseNeed::query()->where('cost_list_line_id', $line->getKey())->firstOrFail();

        return $need;
    }

    /**
     * Satin alma talebi (sat-03, D-190) ya da satin almanin elle actigi kalem.
     *
     * Satin alma talebi genel taleplerden ayridir: urun / hizmet (katalogda
     * varsa o kalem secilir; yoksa talep edilen ad yazilir, satin alma sonra
     * katalogla eslestirir), istenen marka / model ve ozellikler (or.
     * bilgisayar icin islemci, bellek), kullanim yeri (Sirket ici / Saha),
     * gerekce ve "var olan neden kullanilmadi" zorunludur.
     *
     * @param  array<string, mixed>  $data  source, catalog_item_id | requested_item_name, usage_area, brand_model,
     *                                      specification, justification, existing_check, quantity, unit, needed_by,
     *                                      is_urgent, note, org_unit_id, project_id
     */
    public function create(array $data): Model
    {
        $actor = $this->actorModel();
        $source = PurchaseNeedSource::tryFrom((string) ($data['source'] ?? '')) ?? PurchaseNeedSource::OfficeRequest;

        if ($source === PurchaseNeedSource::ProposalCost) {
            throw InvalidTransitionException::make();
        }

        /** @var CatalogItem|null $item */
        $item = filled($data['catalog_item_id'] ?? null) ? CatalogItem::query()->find((int) $data['catalog_item_id']) : null;
        $requestedName = trim((string) ($data['requested_item_name'] ?? ''));

        if (! $item instanceof CatalogItem && $requestedName === '') {
            throw PurchaseRequestIncompleteException::make();
        }

        if ($source === PurchaseNeedSource::OfficeRequest && blank($data['justification'] ?? null)) {
            throw PurchaseRequestIncompleteException::make();
        }

        $usage = self::usageValue($data['usage_area'] ?? null) ?? $item?->usageEnum()?->value;

        // D-230: elle acilan kalem ve tek kalemli talep Saha'da projeyle acilir
        // (kalemli talebin satirlari basliktan gelir, baslik ayrica denetlenir).
        if (in_array($source, [PurchaseNeedSource::Manual, PurchaseNeedSource::OfficeRequest], true) && blank($data['purchase_request_id'] ?? null)) {
            PurchaseRequestService::assertProject($usage, $data['project_id'] ?? null);
        }

        return parent::create([
            ...array_intersect_key($data, array_flip(self::EDITABLE)),
            'source' => $source->value,
            'usage_area' => $usage,
            'catalog_item_id' => $item?->getKey(),
            'requested_item_name' => $item instanceof CatalogItem ? null : mb_substr($requestedName, 0, 300),
            'title' => filled($data['title'] ?? null) ? (string) $data['title'] : ($item instanceof CatalogItem ? (string) $item->getAttribute('name') : $requestedName),
            // D-194 (B54): kalemin birimi sabittir; kalem yoksa secilen birim ya da metinden cozulen.
            ...$this->units->apply([
                'uom_id' => $data['uom_id'] ?? null,
                'unit' => filled($data['unit'] ?? null) ? $data['unit'] : $item?->getAttribute('unit'),
            ], $item),
            'org_unit_id' => $data['org_unit_id'] ?? $actor?->getAttribute('org_unit_id'),
            'requested_by_personnel_id' => $actor?->getKey(),
            'status' => PurchaseNeedStatus::Open->value,
            // D-192 (B53): kalemli talebin satiri ve formdaki sirasi.
            ...(SchemaReadiness::hasBatch('B53') && filled($data['purchase_request_id'] ?? null) ? [
                'purchase_request_id' => (int) $data['purchase_request_id'],
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ] : []),
        ]);
    }

    /**
     * Satin alma teklifi / siparis akisinin kalem durumunu ilerletmesi (D-192):
     * teklife eklendi (Teklif toplaniyor), teklif secildi (Onerilen), siparis
     * onaya gitti / onaylandi / verildi / tamamlandi. Ayni durumdaysa bir sey
     * yapmaz. Kalemli talebin durumu da tazelenir.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $extra
     */
    public function advance(PurchaseNeed $need, PurchaseNeedStatus $to, string $operation, array $attributes = [], array $extra = []): PurchaseNeed
    {
        if ($need->statusEnum() === $to && $attributes === []) {
            return $need;
        }

        return $this->transition($need, $to, $operation, $attributes, $extra);
    }

    /**
     * Teklif secimi / siparis geri alininca kalem, hala acik bir satin alma
     * teklifinde varsa Teklif toplaniyor'a, yoksa Siparis bekliyor'a doner
     * (D-192). Kapanmis ya da siparisi verilmis kaleme dokunulmaz.
     */
    public function revertToQuoting(PurchaseNeed $need, ?int $exceptQuotationId = null, bool $afterOrderCancel = false): PurchaseNeed
    {
        $need->refresh();

        if ($need->isArchived() || $need->statusEnum()->isClosed() || $need->statusEnum() === PurchaseNeedStatus::AwaitingProject) {
            return $need;
        }

        // Siparise girmis kalem yalniz siparis iptal edilince geri doner.
        $inOrder = in_array($need->statusEnum(), [PurchaseNeedStatus::AwaitingApproval, PurchaseNeedStatus::Approved, PurchaseNeedStatus::Ordered], true);

        if ($inOrder && ! $afterOrderCancel) {
            return $need;
        }

        // Kalem baska bir secili teklifte duruyorsa Onerilen kalir.
        $stillSelected = $need->statusEnum() === PurchaseNeedStatus::Recommended && $need->quotationLines()
            ->where('is_cancelled', false)
            ->whereHas('quotation', function ($quotation) use ($exceptQuotationId): void {
                $quotation->where('status', PurchaseQuotationStatus::Selected->value);

                if ($exceptQuotationId !== null) {
                    $quotation->whereKeyNot($exceptQuotationId);
                }
            })
            ->exists();

        if ($stillSelected) {
            return $need;
        }

        $inQuotation = $need->quotationLines()
            ->whereHas('quotation', function ($quotation) use ($exceptQuotationId): void {
                $quotation->whereIn('status', [
                    PurchaseQuotationStatus::Draft->value,
                    PurchaseQuotationStatus::Received->value,
                    PurchaseQuotationStatus::Selected->value,
                ]);

                if ($exceptQuotationId !== null) {
                    $quotation->whereKeyNot($exceptQuotationId);
                }
            })
            ->exists();

        return $this->transition($need, $inQuotation ? PurchaseNeedStatus::Quoting : PurchaseNeedStatus::Open, 'quotation_released', [
            'recommended_by_personnel_id' => null,
            'recommended_at' => null,
            'approval_request_id' => null,
            'ordered_by_personnel_id' => null,
            'ordered_at' => null,
        ]);
    }

    /**
     * Katalogda olmayan urunle acilmis talebi katalog kalemine baglar (D-190):
     * var olan kalem secilir (talep edilen yazim esleme adi olur) ya da bir kez
     * yeni kalem acilir (CatalogItemService; ayni ad varsa ona baglanir).
     */
    public function linkCatalogItem(PurchaseNeed $need, ?int $catalogItemId, ?string $newKind = null): PurchaseNeed
    {
        return $this->transactions->run(function () use ($need, $catalogItemId, $newKind): PurchaseNeed {
            /** @var PurchaseNeed $locked */
            $locked = $this->lockForUpdate($need);
            $this->assertOpen($locked);
            $name = (string) ($locked->getAttribute('requested_item_name') ?: $locked->getAttribute('title'));

            if ($catalogItemId !== null) {
                /** @var CatalogItem|null $item */
                $item = CatalogItem::query()->find($catalogItemId);

                if (! $item instanceof CatalogItem) {
                    throw RecordNotFoundException::make();
                }

                $this->catalog->link($item, $name);
            } else {
                $kind = CatalogItemKind::tryFrom((string) $newKind) ?? CatalogItemKind::Product;
                // D-194 (B54): yeni kalem talebin birimiyle acilir.
                $context = [
                    'usage' => $locked->usageEnum()?->value,
                    'unit' => $locked->getAttribute('unit'),
                    'uom_id' => UnitResolver::enabled() ? $locked->getAttribute('uom_id') : null,
                ];
                $match = $this->catalog->match($name, $kind, $context);
                // Ayni ad katalogda varsa ona baglanir; benzer olsa da kullanici "yeni" dedi.
                $item = $match['status'] === CatalogMatchStatus::Similar
                    ? $this->catalog->createFromExcel($name, $kind, $context)
                    : $match['item'];

                if ($item instanceof CatalogItem && $match['status'] !== CatalogMatchStatus::Matched) {
                    $item->forceFill([
                        'brand' => $locked->getAttribute('brand_model'),
                        'specification' => $locked->getAttribute('specification'),
                    ])->save();
                }
            }

            $locked->forceFill([
                'catalog_item_id' => $item?->getKey(),
                // D-194 (B54): baglanan kalemin birimi varsa o (urunun birimi sabittir).
                ...$this->units->apply([
                    'uom_id' => UnitResolver::enabled() ? $locked->getAttribute('uom_id') : null,
                    'unit' => $locked->getAttribute('unit') ?? $item?->getAttribute('unit'),
                ], $item instanceof CatalogItem ? $item : null),
                'usage_area' => $locked->getAttribute('usage_area') ?? $item?->usageEnum()?->value,
            ]);
            $this->saveWithoutVersion($locked);
            $this->recordActivity($locked, 'catalog_linked', ['kalem' => $item?->getAttribute('name')]);

            return $locked;
        });
    }

    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var PurchaseNeed $need */
            $need = $this->lockForUpdate($record);
            $this->assertOpen($need);

            // Onaya gittikten sonra miktar degismez (onay tutari degisir).
            if (in_array($need->statusEnum(), [PurchaseNeedStatus::AwaitingApproval, PurchaseNeedStatus::Approved, PurchaseNeedStatus::Ordered], true)) {
                unset($data['quantity']);
            }

            $data = array_intersect_key($data, array_flip([...self::EDITABLE, 'row_version']));

            // D-194 (B54): birim verildiyse kalemin birimi sabittir, yoksa secilen birim.
            if (array_key_exists('uom_id', $data) || array_key_exists('unit', $data)) {
                $data = $this->units->apply($data, $need->catalogItem);
            }

            return parent::update($need, $data);
        });
    }

    /**
     * Proje onayi: teklif grubunun gonderdigi kalemler satin almanin listesine duser.
     *
     * @param  iterable<PurchaseNeed>  $needs
     */
    public function approveProject(iterable $needs): int
    {
        return $this->transactions->run(function () use ($needs): int {
            $count = 0;

            foreach ($needs as $need) {
                /** @var PurchaseNeed $locked */
                $locked = $this->lockForUpdate($need);

                if ($locked->statusEnum() !== PurchaseNeedStatus::AwaitingProject) {
                    continue;
                }

                $this->transition($locked, PurchaseNeedStatus::Open, 'project_approved', [
                    'project_approved_by_personnel_id' => $this->actor->personnelId(),
                    'project_approved_at' => Carbon::now('UTC'),
                ]);
                $count++;
            }

            return $count;
        });
    }

    /** Ilk tedarikci teklifi geldiginde kalem "Teklif toplaniyor" olur. */
    public function markQuoting(PurchaseNeed $need): void
    {
        if ($need->statusEnum() === PurchaseNeedStatus::Open) {
            $need->forceFill(['status' => PurchaseNeedStatus::Quoting->value]);
            $this->saveWithoutVersion($need);
        }

        // Kalemli talep teklife girince Isleniyor olur (13 Ekim 2026: talep Acik kaliyordu).
        $this->syncRequestStatus($need);
    }

    /** Satin almanin en uygun buldugu teklif (onerilen). */
    public function recommend(PurchaseNeed $need, PurchaseQuote $quote): PurchaseNeed
    {
        return $this->transactions->run(function () use ($need, $quote): PurchaseNeed {
            /** @var PurchaseNeed $locked */
            $locked = $this->lockForUpdate($need);
            $this->assertOpen($locked);
            $this->assertProjectApproved($locked);
            $this->assertCatalogLinked($locked);

            if ((int) $quote->getAttribute('purchase_need_id') !== (int) $locked->getKey()) {
                throw RecordNotFoundException::make();
            }

            if (in_array($locked->statusEnum(), [PurchaseNeedStatus::Ordered], true)) {
                throw InvalidTransitionException::make();
            }

            $this->closeOpenApproval($locked);

            return $this->transition($locked, PurchaseNeedStatus::Recommended, 'recommended', [
                'recommended_quote_id' => $quote->getKey(),
                'recommended_by_personnel_id' => $this->actor->personnelId(),
                'recommended_at' => Carbon::now('UTC'),
                'approval_request_id' => null,
            ], ['tedarikci' => $quote->supplierLabel(), 'tutar' => (float) $quote->getAttribute('total_amount'), 'para_birimi' => $quote->getAttribute('currency_code')]);
        });
    }

    /** Onerilen teklif silinir / degisirse kalem geri doner. */
    public function forgetRecommendation(PurchaseNeed $need): void
    {
        $need->refresh();

        if ($need->statusEnum()->isClosed() || $need->statusEnum() === PurchaseNeedStatus::Ordered) {
            return;
        }

        $this->closeOpenApproval($need);
        $hasQuotes = $need->quotes()->exists();
        $need->forceFill([
            'recommended_quote_id' => null,
            'recommended_by_personnel_id' => null,
            'recommended_at' => null,
            'approval_request_id' => null,
            'status' => ($hasQuotes ? PurchaseNeedStatus::Quoting : PurchaseNeedStatus::Open)->value,
        ]);
        $this->saveWithoutVersion($need);
    }

    public function approvalState(PurchaseNeed $need): PurchaseApprovalState
    {
        return $this->approvals->state($need);
    }

    /**
     * Onaya gonderir. Onay gerekmeyen kalemde bir sey yapmaz; onerilen teklif ve
     * dovizde TL kuru gerekir. Tek adimli politikada onayci kisinin kendisiyse
     * (yetkisi dahilinde) talep acilmadan onaylanir.
     */
    public function requestApproval(PurchaseNeed $need, ?string $note = null): PurchaseNeed
    {
        return $this->transactions->run(function () use ($need, $note): PurchaseNeed {
            /** @var PurchaseNeed $locked */
            $locked = $this->lockForUpdate($need);
            $this->assertOpen($locked);
            $this->assertProjectApproved($locked);
            $this->assertCatalogLinked($locked);
            $state = $this->approvals->state($locked);

            if (! $state->required || $state->satisfied) {
                return $locked;
            }

            match ($state->missing) {
                PurchaseApprovalState::MISSING_QUOTE => throw PurchaseQuoteRequiredException::make(),
                PurchaseApprovalState::MISSING_RATE => throw PurchaseQuoteRateRequiredException::make(),
                default => null,
            };

            if ($state->selfApprovable) {
                return $this->transition($locked, PurchaseNeedStatus::Approved, 'self_approved', [], ['tutar_tl' => $state->amountTry]);
            }

            $request = $this->approvalRequests->request(PurchaseNeedSubject::TYPE, (int) $locked->getKey(), null, null, $note);

            return $this->transition($locked, PurchaseNeedStatus::AwaitingApproval, 'approval_requested', [
                'approval_request_id' => $request->getKey(),
            ], ['tutar_tl' => $state->amountTry, 'merci' => $state->approversText()]);
        });
    }

    /** Onay motorunun sonucu (PurchaseNeedSubject). */
    public function applyApproval(int $needId, string $outcome, ?string $comment): void
    {
        /** @var PurchaseNeed|null $need */
        $need = PurchaseNeed::query()->find($needId);

        if ($need === null || $need->statusEnum() !== PurchaseNeedStatus::AwaitingApproval) {
            return;
        }

        $next = $outcome === 'approved' ? PurchaseNeedStatus::Approved : PurchaseNeedStatus::Recommended;
        $this->transition($need, $next, 'approval_'.$outcome, [], $comment !== null ? ['aciklama' => $comment] : []);
    }

    /**
     * Siparis verildi. Onay gereken kalemde onay saglanmadan verilemez.
     *
     * @param  array{expected_delivery_on?: string|null, note?: string|null}  $data
     */
    public function placeOrder(PurchaseNeed $need, array $data = []): PurchaseNeed
    {
        return $this->transactions->run(function () use ($need, $data): PurchaseNeed {
            /** @var PurchaseNeed $locked */
            $locked = $this->lockForUpdate($need);
            $this->assertOpen($locked);
            $this->assertProjectApproved($locked);
            $this->assertCatalogLinked($locked);

            if ($locked->statusEnum() === PurchaseNeedStatus::Ordered) {
                throw InvalidTransitionException::make();
            }

            $this->assertApproved($locked);
            $quote = $locked->recommendedQuote;

            return $this->transition($locked, PurchaseNeedStatus::Ordered, 'ordered', [
                'ordered_by_personnel_id' => $this->actor->personnelId(),
                'ordered_at' => Carbon::now('UTC'),
                'expected_delivery_on' => $data['expected_delivery_on'] ?? $quote?->getAttribute('delivery_on'),
                'note' => filled($data['note'] ?? null) ? trim((string) $locked->getAttribute('note')."\n".$data['note']) : $locked->getAttribute('note'),
            ]);
        });
    }

    /**
     * Siparis tamamlandi (teslim alindi). Onay gereken kalemde onay olmadan
     * tamamlanamaz. Sirket ici kalem Sirket ici alimlar'a yazilir.
     */
    public function complete(PurchaseNeed $need, ?string $purchasedOn = null): PurchaseNeed
    {
        return $this->transactions->run(function () use ($need, $purchasedOn): PurchaseNeed {
            /** @var PurchaseNeed $locked */
            $locked = $this->lockForUpdate($need);
            $this->assertOpen($locked);
            $this->assertProjectApproved($locked);
            $this->assertCatalogLinked($locked);
            $this->assertApproved($locked);

            $completed = $this->transition($locked, PurchaseNeedStatus::Completed, 'completed', [
                'completed_by_personnel_id' => $this->actor->personnelId(),
                'completed_at' => Carbon::now('UTC'),
                'ordered_at' => $locked->getAttribute('ordered_at') ?? Carbon::now('UTC'),
                'ordered_by_personnel_id' => $locked->getAttribute('ordered_by_personnel_id') ?? $this->actor->personnelId(),
            ]);

            $this->recordCompanyPurchase($completed, $purchasedOn);

            return $completed;
        });
    }

    public function cancel(PurchaseNeed $need, ?string $reason = null): PurchaseNeed
    {
        return $this->transactions->run(function () use ($need, $reason): PurchaseNeed {
            /** @var PurchaseNeed $locked */
            $locked = $this->lockForUpdate($need);
            $this->assertOpen($locked);
            $this->closeOpenApproval($locked);

            return $this->transition($locked, PurchaseNeedStatus::Cancelled, 'cancelled', [
                'cancelled_at' => Carbon::now('UTC'),
            ], filled($reason) ? ['gerekce' => $reason] : []);
        });
    }

    public function archive(Model|int|string $record): PurchaseNeed
    {
        return $this->transactions->run(function () use ($record): PurchaseNeed {
            /** @var PurchaseNeed $need */
            $need = $this->lockForUpdate($record);

            if ($need->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $this->closeOpenApproval($need);
            $need->forceFill(['archived_at' => Carbon::now('UTC')]);
            $this->saveWithoutVersion($need);
            $this->recordActivity($need, 'archived');

            return $need;
        });
    }

    public function restore(Model|int|string $record): PurchaseNeed
    {
        return $this->transactions->run(function () use ($record): PurchaseNeed {
            /** @var PurchaseNeed $need */
            $need = $this->lockForUpdate($record);

            if (! $need->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $need->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($need);
            $this->recordActivity($need, 'restored');

            return $need;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version']);

        // D-194: sabit birim kolonu B54 ile gelir.
        if (! UnitResolver::enabled()) {
            unset($data['uom_id']);
        }

        // D-190 alanlari B52A ile gelir.
        if (! SchemaReadiness::hasBatch('B52A')) {
            unset($data['usage_area'], $data['requested_item_name'], $data['brand_model'], $data['specification'], $data['justification'], $data['existing_check']);
        } elseif (array_key_exists('usage_area', $data)) {
            $data['usage_area'] = self::usageValue($data['usage_area']);
        }

        foreach (['brand_model', 'specification', 'justification', 'existing_check'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? trim((string) $data[$field]) : null;
            }
        }

        if (array_key_exists('title', $data)) {
            $data['title'] = Str::limit(trim((string) $data['title']), 495, '');
        }

        if (array_key_exists('quantity', $data) && (float) $data['quantity'] <= 0) {
            $data['quantity'] = 1;
        }

        if (array_key_exists('is_urgent', $data)) {
            $data['is_urgent'] = (bool) $data['is_urgent'];
        }

        foreach (['needed_by', 'unit', 'note', 'org_unit_id', 'project_id'] as $field) {
            if (array_key_exists($field, $data) && blank($data[$field])) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $source = $record->getAttribute('source');

        return [
            'kalem' => Str::limit((string) $record->getAttribute('title'), 120),
            'kaynak' => $source instanceof PurchaseNeedSource ? $source->value : $source,
            'teklif' => $record->getAttribute('proposal_id'),
            'miktar' => (float) $record->getAttribute('quantity'),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $extra
     */
    private function transition(PurchaseNeed $need, PurchaseNeedStatus $to, string $operation, array $attributes = [], array $extra = []): PurchaseNeed
    {
        $from = $need->statusEnum();
        $need->forceFill([...$attributes, 'status' => $to->value]);
        $this->saveWithoutVersion($need);
        $this->recordActivity($need, $operation, [...$extra, 'durum' => ['onceki' => $from->value, 'yeni' => $to->value]]);
        $this->syncRequestStatus($need);

        return $need;
    }

    /**
     * Kalemli talebin durumu kalemlerinden hesaplanir (D-192): hepsi kapandiysa
     * Tamamlandi (hepsi iptalse Iptal), en az biri islenmisse Isleniyor, yoksa
     * Acik. Talebin kendi iptali PurchaseRequestService'tedir.
     */
    private function syncRequestStatus(PurchaseNeed $need): void
    {
        $requestId = SchemaReadiness::hasBatch('B53') ? $need->getAttribute('purchase_request_id') : null;

        if ($requestId === null) {
            return;
        }

        /** @var PurchaseRequest|null $request */
        $request = PurchaseRequest::query()->lockForUpdate()->find((int) $requestId);

        if ($request === null || $request->statusEnum() === PurchaseRequestStatus::Cancelled) {
            return;
        }

        $statuses = $request->needs()->pluck('status')->map(
            static fn (mixed $status): PurchaseNeedStatus => $status instanceof PurchaseNeedStatus ? $status : (PurchaseNeedStatus::tryFrom((string) $status) ?? PurchaseNeedStatus::Open),
        );

        $untouched = [PurchaseNeedStatus::Open, PurchaseNeedStatus::AwaitingProject];
        $next = match (true) {
            $statuses->isEmpty() => PurchaseRequestStatus::Open,
            $statuses->every(static fn (PurchaseNeedStatus $status): bool => $status === PurchaseNeedStatus::Cancelled) => PurchaseRequestStatus::Cancelled,
            $statuses->every(static fn (PurchaseNeedStatus $status): bool => $status->isClosed()) => PurchaseRequestStatus::Completed,
            $statuses->contains(static fn (PurchaseNeedStatus $status): bool => ! in_array($status, $untouched, true) && $status !== PurchaseNeedStatus::Cancelled) => PurchaseRequestStatus::InProgress,
            default => PurchaseRequestStatus::Open,
        };

        if ($request->statusEnum() !== $next) {
            $request->forceFill(['status' => $next->value])->save();
        }
    }

    private function assertOpen(PurchaseNeed $need): void
    {
        if ($need->isArchived() || $need->statusEnum()->isClosed()) {
            throw PurchaseNeedClosedException::make();
        }
    }

    /** Talep katalogda olmayan bir urunle acildiysa once katalogla eslestirilir (D-190). */
    private function assertCatalogLinked(PurchaseNeed $need): void
    {
        if (! $need->hasCatalogItem()) {
            throw CatalogItemRequiredException::make();
        }
    }

    private static function usageValue(mixed $value): ?string
    {
        $value = $value instanceof \BackedEnum ? (string) $value->value : (filled($value) ? (string) $value : null);

        return $value !== null && UsageArea::tryFrom($value) !== null ? $value : null;
    }

    private function assertProjectApproved(PurchaseNeed $need): void
    {
        if ($need->statusEnum() === PurchaseNeedStatus::AwaitingProject) {
            throw ProjectApprovalPendingException::make();
        }
    }

    private function assertApproved(PurchaseNeed $need): void
    {
        $state = $this->approvals->state($need);

        if ($state->required && ! $state->satisfied) {
            throw PurchaseApprovalRequiredException::make(['approvers' => $state->approvers === [] ? __('procurement.approval.policy_text') : $state->approversText()]);
        }
    }

    private function closeOpenApproval(PurchaseNeed $need): void
    {
        $request = $need->approvalRequest;

        if ($request instanceof ApprovalRequest && in_array($request->getAttribute('status'), [ApprovalRequestStatus::Pending, ApprovalRequestStatus::InProgress], true)) {
            $this->approvalRequests->cancel($request, __('procurement.approval.cancelled_reason'));
        }
    }

    /** Tamamlanan sirket ici kalemi Sirket ici alimlar'a yazar (bir kez). */
    private function recordCompanyPurchase(PurchaseNeed $need, ?string $purchasedOn): void
    {
        $item = $need->catalogItem;
        // D-190: Sirket ici alimlar kullanim yerine gore (tur Urun / Hizmet kalir).
        $isCompany = $need->usageEnum() === UsageArea::Office
            || (! SchemaReadiness::hasBatch('B52A') && $need->sourceEnum() === PurchaseNeedSource::OfficeRequest);

        if (! $isCompany || $need->companyPurchase()->exists()) {
            return;
        }

        $quote = $need->recommendedQuote;
        $quantity = (float) $need->getAttribute('quantity');

        $this->companyPurchases->create([
            'catalog_item_id' => $need->getAttribute('catalog_item_id'),
            'purchase_need_id' => $need->getKey(),
            'org_unit_id' => $need->getAttribute('org_unit_id'),
            'personnel_id' => $need->getAttribute('requested_by_personnel_id'),
            'supplier_party_id' => $quote?->getAttribute('supplier_party_id'),
            'supplier_name' => $quote?->getAttribute('supplier_name'),
            'description' => $need->getAttribute('title'),
            'purchased_on' => $purchasedOn ?? Carbon::now(DisplayTime::zone())->toDateString(),
            'quantity' => $quantity > 0 ? $quantity : 1,
            'unit_price' => $quote instanceof PurchaseQuote ? (float) $quote->getAttribute('unit_price') : (float) ($need->getAttribute('estimated_unit_cost') ?? 0),
            'currency_code' => $quote?->getAttribute('currency_code') ?? $need->getAttribute('estimated_currency_code') ?? 'TRY',
            'renewal_months' => $item?->getAttribute('renewal_months'),
        ]);
    }

    private function projectIdOf(?Proposal $proposal): ?int
    {
        $case = $proposal?->businessCase;

        if (! $case instanceof BusinessCase) {
            return null;
        }

        $project = $case->project()->first();

        return $project instanceof Project ? (int) $project->getKey() : null;
    }

    private function actorModel(): ?Personnel
    {
        $id = $this->actor->personnelId();

        // D-148: kimlige burunulmus oturumda gizli hesap kapsami atlanir.
        return $id !== null ? Personnel::query()->withoutGlobalScopes()->find($id) : null;
    }
}
