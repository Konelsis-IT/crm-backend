<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Kartin bagli kaydi (B36, D-115): teklif, ihale ilani, belge, tedarik
 * kalemi, talep, gorusme ya da is dosyasi. Her tur kendi FK kolonundadir
 * (`linked_*_id`); polimorfik bag yoktur.
 */
enum WorkItemLinkKind: string implements HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case None = 'none';
    case BusinessCase = 'business_case';
    case Proposal = 'proposal';
    case TenderNotice = 'tender_notice';
    case Document = 'document';
    case SupplyItem = 'supply_item';
    case WorkRequest = 'work_request';
    case MeetingPlan = 'meeting_plan';
    // D-219 (B57): satin alinacak kalem; her kalem bir is olarak da gorunur.
    case PurchaseNeed = 'purchase_need';
    // D-235 (B60): satin alma siparisi ve urun / hizmet (Malzeme ve Hizmetler).
    case PurchaseOrder = 'purchase_order';
    case CatalogItem = 'catalog_item';

    /** FK kolonu; None icin null. */
    public function column(): ?string
    {
        return match ($this) {
            self::None => null,
            self::BusinessCase => 'linked_business_case_id',
            self::Proposal => 'linked_proposal_id',
            self::TenderNotice => 'linked_tender_notice_id',
            self::Document => 'linked_document_id',
            self::SupplyItem => 'linked_supply_item_id',
            self::WorkRequest => 'linked_work_request_id',
            self::MeetingPlan => 'linked_meeting_plan_id',
            self::PurchaseNeed => 'linked_purchase_need_id',
            self::PurchaseOrder => 'linked_purchase_order_id',
            self::CatalogItem => 'linked_catalog_item_id',
        };
    }

    /** WorkItem iliski adi; None icin null. */
    public function relation(): ?string
    {
        return match ($this) {
            self::None => null,
            self::BusinessCase => 'linkedBusinessCase',
            self::Proposal => 'linkedProposal',
            self::TenderNotice => 'linkedTenderNotice',
            self::Document => 'linkedDocument',
            self::SupplyItem => 'linkedSupplyItem',
            self::WorkRequest => 'linkedWorkRequest',
            self::MeetingPlan => 'linkedMeetingPlan',
            self::PurchaseNeed => 'linkedPurchaseNeed',
            self::PurchaseOrder => 'linkedPurchaseOrder',
            self::CatalogItem => 'linkedCatalogItem',
        };
    }

    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return array_values(array_filter(array_map(
            static fn (self $kind): ?string => $kind->available() ? $kind->column() : null,
            self::cases(),
        )));
    }

    /** Kolonu veritabaninda var mi (satin alinacak kalem B57, siparis ve urun / hizmet B60 ile gelir). */
    public function available(): bool
    {
        return match ($this) {
            self::PurchaseNeed => SchemaReadiness::hasBatch('B57'),
            // B60A: link_kind kontrol kurali bu iki degeri tanimadan ilk bagli kayit olamazlar (D-238).
            self::PurchaseOrder, self::CatalogItem => SchemaReadiness::hasBatch('B60') && SchemaReadiness::hasBatch('B60A'),
            default => true,
        };
    }

    /**
     * Ekranda gosterilir ve secilir mi: satin alinacak kalem Satin alinacaklar
     * acilmadan gorunmez (D-220; D-229: satin alinacaklar 2.9'da).
     */
    public function visible(): bool
    {
        return $this->available() && match ($this) {
            self::PurchaseNeed => FeatureFlags::enabled(Feature::PurchaseNeeds),
            // D-235: satin alma yayina girdikce (siparis 2.9, Malzeme ve Hizmetler 2.8).
            self::PurchaseOrder => FeatureFlags::enabled(Feature::PurchaseOrders),
            self::CatalogItem => FeatureFlags::enabled(Feature::Catalog),
            default => true,
        };
    }

    /**
     * Ek bagli kayit tablosu (D-233, B59): bir ise birden fazla teklif, is
     * gelistirme ve ihale. Diger turler tek kayittir; null doner.
     *
     * @return array{table: string, column: string, relation: string}|null
     */
    public function extra(): ?array
    {
        return match ($this) {
            self::Proposal => ['table' => 'work_item_proposals', 'column' => 'proposal_id', 'relation' => 'extraProposals'],
            self::BusinessCase => ['table' => 'work_item_business_cases', 'column' => 'business_case_id', 'relation' => 'extraBusinessCases'],
            self::TenderNotice => ['table' => 'work_item_tender_notices', 'column' => 'tender_notice_id', 'relation' => 'extraTenderNotices'],
            // D-235 (B60): diger turler de birden fazla baglanir.
            self::Document => ['table' => 'work_item_documents', 'column' => 'document_id', 'relation' => 'extraDocuments'],
            self::WorkRequest => ['table' => 'work_item_work_requests', 'column' => 'work_request_id', 'relation' => 'extraWorkRequests'],
            self::MeetingPlan => ['table' => 'work_item_meeting_plans', 'column' => 'meeting_plan_id', 'relation' => 'extraMeetingPlans'],
            self::PurchaseNeed => ['table' => 'work_item_purchase_needs', 'column' => 'purchase_need_id', 'relation' => 'extraPurchaseNeeds'],
            self::PurchaseOrder => ['table' => 'work_item_purchase_orders', 'column' => 'purchase_order_id', 'relation' => 'extraPurchaseOrders'],
            self::CatalogItem => ['table' => 'work_item_catalog_items', 'column' => 'catalog_item_id', 'relation' => 'extraCatalogItems'],
            default => null,
        };
    }

    /**
     * Birden fazla baglanabilen turler (D-233: teklif, is gelistirme, ihale;
     * D-235: belge, talep, gorusme ve satin alma turleri de). Ek kayit tablosu
     * uygulanmis olan turler doner: B59 ilk uc tur, B60 digerleri.
     *
     * @return list<self>
     */
    public static function multiple(): array
    {
        $kinds = [self::Proposal, self::BusinessCase, self::TenderNotice];

        if (SchemaReadiness::hasBatch('B60')) {
            foreach ([self::Document, self::WorkRequest, self::MeetingPlan, self::PurchaseNeed, self::PurchaseOrder, self::CatalogItem] as $kind) {
                if ($kind->available()) {
                    $kinds[] = $kind;
                }
            }
        }

        return $kinds;
    }

    /**
     * @return list<string>
     */
    public static function relations(): array
    {
        return array_values(array_filter(array_map(
            static fn (self $kind): ?string => $kind->relation(),
            self::cases(),
        )));
    }

    /**
     * Secilebilir turler (None haric).
     *
     * @return array<string, string>
     */
    public static function linkOptions(): array
    {
        $options = self::options();
        unset($options[self::None->value]);

        foreach ([self::PurchaseNeed, self::PurchaseOrder, self::CatalogItem] as $kind) {
            if (! $kind->visible()) {
                unset($options[$kind->value]);
            }
        }

        return $options;
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::None => Heroicon::OutlinedMinus,
            self::BusinessCase => Heroicon::OutlinedFolderOpen,
            self::Proposal => Heroicon::OutlinedDocumentCurrencyDollar,
            self::TenderNotice => Heroicon::OutlinedMegaphone,
            self::Document => Heroicon::OutlinedDocumentText,
            self::SupplyItem => Heroicon::OutlinedTruck,
            self::WorkRequest => Heroicon::OutlinedInboxArrowDown,
            self::MeetingPlan => Heroicon::OutlinedChatBubbleLeftRight,
            self::PurchaseNeed => Heroicon::OutlinedShoppingCart,
            self::PurchaseOrder => Heroicon::OutlinedClipboardDocumentCheck,
            self::CatalogItem => Heroicon::OutlinedCube,
        };
    }
}
