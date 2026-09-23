<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
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
        };
    }

    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return array_values(array_filter(array_map(
            static fn (self $kind): ?string => $kind->column(),
            self::cases(),
        )));
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
        };
    }
}
