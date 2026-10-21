<?php

declare(strict_types=1);

namespace App\Query\Procurement;

use App\Enums\Procurement\QuotationDocumentAiStatus;
use App\Enums\Party\PartyStatus;
use App\Models\Party\Party;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseOrderLine;
use App\Models\Procurement\PurchaseQuotation;
use App\Models\Procurement\PurchaseQuotationDocument;
use App\Models\Procurement\PurchaseQuotationLine;
use App\Models\Reference\Currency;
use App\Query\Party\PartyQueries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Konelsis AI teklif okumasinin okumalari (D-192): bekleyen belgeler, tedarikci
 * eslesmesi (vergi no, sonra ad), AI'nin actigi kalemler ve kalem kullanim
 * denetimi. Ekran ve servis siniflari sorgu yazmaz.
 */
final class QuotationAiQueries
{
    public function __construct(private readonly PartyQueries $parties) {}

    /**
     * Konelsis AI'ye gidecek (AI bekliyor) belgeler, eklenis sirasiyla.
     *
     * @return Collection<int, PurchaseQuotationDocument>
     */
    public function pendingDocuments(PurchaseQuotation $quotation): Collection
    {
        return PurchaseQuotationDocument::query()
            ->with('revision')
            ->where('purchase_quotation_id', $quotation->getKey())
            ->where('ai_status', QuotationDocumentAiStatus::Pending->value)
            ->orderBy('id')
            ->get();
    }

    /**
     * Ekranda gosterilecek AI belgeleri (son okumanin belgeleri once).
     *
     * @return Collection<int, PurchaseQuotationDocument>
     */
    public function aiDocuments(PurchaseQuotation $quotation): Collection
    {
        return PurchaseQuotationDocument::query()
            ->with('revision')
            ->where('purchase_quotation_id', $quotation->getKey())
            ->whereNotNull('ai_status')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Tedarikci tarafi: once vergi numarasi (yalniz rakamlar esit), sonra ayni ad
     * (kisa / uzun ad dahil). Arsivdeki ya da birlestirilmis taraf onerilmez.
     */
    public function supplierPartyId(?string $taxNumber, ?string $name): ?int
    {
        // D-232: tedarikci listesi kuruluysa yalniz satin alma tedarikcileri aranir.
        if (PurchaseSupplierQueries::enabled()) {
            return app(PurchaseSupplierQueries::class)->partyIdByTaxOrName($taxNumber, $name);
        }

        $digits = preg_replace('/\D+/', '', (string) $taxNumber) ?? '';

        if (strlen($digits) >= 10) {
            $party = Party::query()
                ->whereNull('archived_at')
                ->where('status', '<>', PartyStatus::Merged->value)
                ->whereHas('organizationProfile', fn (Builder $profile): Builder => $profile
                    ->whereRaw("REPLACE(REPLACE(REPLACE(tax_number, ' ', ''), '-', ''), '.', '') = ?", [$digits]))
                ->orderBy('id')
                ->first(['id']);

            if ($party instanceof Party) {
                return (int) $party->getKey();
            }
        }

        $same = filled($name) ? $this->parties->sameName((string) $name) : null;

        return $same !== null && ! $same['archived'] ? $same['id'] : null;
    }

    /** CRM'de tanimli (aktif ya da pasif) para birimi mi? Teklif kolonunun FK'si. */
    public function currencyExists(?string $code): bool
    {
        return filled($code) && Currency::query()->whereKey(strtoupper((string) $code))->exists();
    }

    /** Teklifte fiyati girilmis (0'dan buyuk) kalem var mi? */
    public function hasPricedLines(PurchaseQuotation $quotation): bool
    {
        return PurchaseQuotationLine::query()
            ->where('purchase_quotation_id', $quotation->getKey())
            ->where('unit_price', '>', 0)
            ->exists();
    }

    /**
     * Konelsis AI'nin bu teklif icin actigi ve henuz kabul edilmemis kalemler.
     *
     * @return Collection<int, PurchaseQuotationLine>
     */
    public function aiCreatedLines(PurchaseQuotation $quotation): Collection
    {
        return PurchaseQuotationLine::query()
            ->with('catalogItem.category.parent')
            ->where('purchase_quotation_id', $quotation->getKey())
            ->where('ai_created_item', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function aiCreatedCount(PurchaseQuotation $quotation): int
    {
        return PurchaseQuotationLine::query()
            ->where('purchase_quotation_id', $quotation->getKey())
            ->where('ai_created_item', true)
            ->count();
    }

    /** Kalem baska bir teklif ya da siparis satirinda kullaniliyor mu? */
    public function itemUsedElsewhere(int $catalogItemId, int $exceptLineId): bool
    {
        return PurchaseQuotationLine::query()->where('catalog_item_id', $catalogItemId)->whereKeyNot($exceptLineId)->exists()
            || PurchaseOrderLine::query()->where('catalog_item_id', $catalogItemId)->exists();
    }

    public function activeItem(int $catalogItemId): ?CatalogItem
    {
        /** @var CatalogItem|null $item */
        $item = CatalogItem::query()->notArchived()->find($catalogItemId);

        return $item;
    }
}
