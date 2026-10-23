<?php

declare(strict_types=1);

namespace App\Models\Report;

use App\Enums\Report\WorkDiscipline;
use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemPriority;
use App\Enums\Report\WorkItemSource;
use App\Enums\Report\WorkItemStatus;
use App\Enums\Report\WorkWaitingKind;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\TenderNotice;
use App\Models\Activity\PersonnelActivity;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\Document;
use App\Models\Party\MeetingPlan;
use App\Models\Party\Party;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Procurement\CatalogItem;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Project\ProjectSupplyItem;
use App\Models\WorkRequest\WorkRequest;
use App\Policies\WorkItemPolicy;
use App\Reports\Work\WorkCategoryCatalog;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Is panosu karti (B36, D-115). Panonun, Isler listesinin, gunluk/haftalik
 * raporun ve sure raporunun temel birimidir. Durum gecmisi Personel
 * Hareketleri'ndedir (`work_item.status_changed`); `*_seconds` kolonlari o
 * gecmisin izdusumudur ve acik kartin surmekte olan dilimi okunurken eklenir.
 */
#[Table('work_items')]
#[Fillable([
    'title', 'personnel_id', 'org_unit_id', 'project_id', 'parent_id', 'category_code', 'status', 'is_critical',
    'work_at', 'work_on', 'due_on', 'done_on',
    'waiting_kind', 'waiting_personnel_id', 'waiting_party_id', 'waiting_text', 'waiting_since',
    'requester_kind', 'requester_personnel_id', 'requester_party_id', 'requester_text',
    'link_kind', 'linked_business_case_id', 'linked_proposal_id', 'linked_tender_notice_id', 'linked_document_id',
    'linked_supply_item_id', 'linked_work_request_id', 'linked_meeting_plan_id',
    'source', 'source_activity_id', 'work_hours', 'note', 'sort_order',
    // D-219 (B57): oncelik, tum ekip, is grubu, satin alinacak kalem, tekrarli is donemi, eski no.
    'priority', 'for_everyone', 'discipline', 'linked_purchase_need_id', 'recurring_work_id', 'period_key', 'legacy_no',
    'linked_purchase_order_id', 'linked_catalog_item_id',
    'status_changed_at', 'started_at', 'completed_at', 'progress_seconds', 'waiting_seconds', 'blocked_seconds',
])]
#[UsePolicy(WorkItemPolicy::class)]
class WorkItem extends Model
{
    use HasAuditColumns;

    /** Bu kadar gunden uzun bekleyen kart "uzun bekleyen" sayilir. */
    public const LONG_WAIT_DAYS = 7;

    /** Is kodu on eki: IS-YYYY-NNNN (B56, D-204); kodu WorkItemService verir, elle yazilmaz. */
    public const CODE_PREFIX = 'IS';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkItemStatus::class,
            'source' => WorkItemSource::class,
            'link_kind' => WorkItemLinkKind::class,
            'waiting_kind' => WorkWaitingKind::class,
            'requester_kind' => WorkWaitingKind::class,
            'is_critical' => 'boolean',
            'priority' => WorkItemPriority::class,
            'for_everyone' => 'boolean',
            'discipline' => WorkDiscipline::class,
            'work_at' => 'datetime',
            'work_on' => 'date',
            'due_on' => 'date',
            'done_on' => 'date',
            'waiting_since' => 'datetime',
            'status_changed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'work_hours' => 'decimal:2',
            'sort_order' => 'integer',
            'progress_seconds' => 'integer',
            'waiting_seconds' => 'integer',
            'blocked_seconds' => 'integer',
        ];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** Ana is (ust kart). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Alt kartlar. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function waitingPersonnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'waiting_personnel_id');
    }

    public function waitingParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'waiting_party_id');
    }

    /** Talep eden kisi (B37, D-118). */
    public function requesterPersonnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'requester_personnel_id');
    }

    /** Talep eden taraf (musteri, tedarikci, kurum). */
    public function requesterParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'requester_party_id');
    }

    public function linkedBusinessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'linked_business_case_id');
    }

    public function linkedProposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'linked_proposal_id');
    }

    public function linkedTenderNotice(): BelongsTo
    {
        return $this->belongsTo(TenderNotice::class, 'linked_tender_notice_id');
    }

    /** D-233 (B59): ilk bagli kayittan sonraki ek teklifler. */
    public function extraProposals(): BelongsToMany
    {
        return $this->belongsToMany(Proposal::class, 'work_item_proposals', 'work_item_id', 'proposal_id')->orderBy('work_item_proposals.id');
    }

    /** D-233 (B59): ek is gelistirme kayitlari (potansiyel is / yatirimci projesi). */
    public function extraBusinessCases(): BelongsToMany
    {
        return $this->belongsToMany(BusinessCase::class, 'work_item_business_cases', 'work_item_id', 'business_case_id')->orderBy('work_item_business_cases.id');
    }

    /** D-233 (B59): ek ihaleler. */
    public function extraTenderNotices(): BelongsToMany
    {
        return $this->belongsToMany(TenderNotice::class, 'work_item_tender_notices', 'work_item_id', 'tender_notice_id')->orderBy('work_item_tender_notices.id');
    }

    /** D-235 (B60): ek belgeler. */
    public function extraDocuments(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'work_item_documents', 'work_item_id', 'document_id')->orderBy('work_item_documents.id');
    }

    /** D-235 (B60): ek talepler. */
    public function extraWorkRequests(): BelongsToMany
    {
        return $this->belongsToMany(WorkRequest::class, 'work_item_work_requests', 'work_item_id', 'work_request_id')->orderBy('work_item_work_requests.id');
    }

    /** D-235 (B60): ek gorusmeler. */
    public function extraMeetingPlans(): BelongsToMany
    {
        return $this->belongsToMany(MeetingPlan::class, 'work_item_meeting_plans', 'work_item_id', 'meeting_plan_id')->orderBy('work_item_meeting_plans.id');
    }

    /** D-235 (B60): ek satin alinacak kalemler. */
    public function extraPurchaseNeeds(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseNeed::class, 'work_item_purchase_needs', 'work_item_id', 'purchase_need_id')->orderBy('work_item_purchase_needs.id');
    }

    /** D-235 (B60): ek siparisler. */
    public function extraPurchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class, 'work_item_purchase_orders', 'work_item_id', 'purchase_order_id')->orderBy('work_item_purchase_orders.id');
    }

    /** D-235 (B60): ek urun / hizmetler. */
    public function extraCatalogItems(): BelongsToMany
    {
        return $this->belongsToMany(CatalogItem::class, 'work_item_catalog_items', 'work_item_id', 'catalog_item_id')->orderBy('work_item_catalog_items.id');
    }

    public function linkedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'linked_document_id');
    }

    public function linkedSupplyItem(): BelongsTo
    {
        return $this->belongsTo(ProjectSupplyItem::class, 'linked_supply_item_id');
    }

    public function linkedWorkRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class, 'linked_work_request_id');
    }

    public function linkedMeetingPlan(): BelongsTo
    {
        return $this->belongsTo(MeetingPlan::class, 'linked_meeting_plan_id');
    }

    /** D-219 (B57): isin diger sorumlulari (ana sorumlu personnel_id). */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Personnel::class, 'work_item_assignees', 'work_item_id', 'personnel_id')->orderBy('full_name');
    }

    /** D-219 (B57): isin tarihli notlari, en yenisi once. */
    public function notes(): HasMany
    {
        return $this->hasMany(WorkItemNote::class, 'work_item_id')->orderByDesc('noted_at')->orderByDesc('id');
    }

    /** D-219 (B57): isin belgeleri. */
    public function files(): HasMany
    {
        return $this->hasMany(WorkItemFile::class, 'work_item_id')->orderBy('sort_order')->orderBy('id');
    }

    /** D-219 (B57): isin geldigi tekrarli is (gorev ve sorumluluk). */
    public function recurringWork(): BelongsTo
    {
        return $this->belongsTo(RecurringWork::class, 'recurring_work_id');
    }

    /**
     * Diger sorumlularin kimlikleri (D-219); B57 yoksa bos.
     *
     * @return list<int>
     */
    public function assigneeIds(): array
    {
        if (! SchemaReadiness::hasBatch('B57')) {
            return [];
        }

        return $this->assignees->map(fn (Personnel $person): int => (int) $person->getKey())->values()->all();
    }

    /** D-240 (B61): arsivde mi (D-156: yalniz archived_at). */
    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }

    /** D-240: arsivdeki isler pano, listeler, oneriler ve uyarilarda gorunmez (B61 yoksa suzgec yok). */
    public function scopeNotArchived(Builder $query): Builder
    {
        return SchemaReadiness::hasBatch('B61') ? $query->whereNull($query->qualifyColumn('archived_at')) : $query;
    }

    /** Oncelik (B57 oncesi kayitta kritik bayragindan). */
    public function priorityLevel(): WorkItemPriority
    {
        $priority = $this->getAttribute('priority');

        return $priority instanceof WorkItemPriority ? $priority : WorkItemPriority::fromCritical((bool) $this->is_critical);
    }

    /** D-219 (B57): satin alinacak kalem. */
    public function linkedPurchaseNeed(): BelongsTo
    {
        return $this->belongsTo(PurchaseNeed::class, 'linked_purchase_need_id');
    }

    /** D-235 (B60): satin alma siparisi. */
    public function linkedPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'linked_purchase_order_id');
    }

    /** D-235 (B60): urun / hizmet (Malzeme ve Hizmetler kalemi). */
    public function linkedCatalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'linked_catalog_item_id');
    }

    public function sourceActivity(): BelongsTo
    {
        return $this->belongsTo(PersonnelActivity::class, 'source_activity_id');
    }

    /** Bagli kayit (turune gore), varsa. */
    public function linkedRecord(): ?Model
    {
        $relation = $this->link_kind?->relation();

        if ($relation === null) {
            return null;
        }

        $record = $this->getRelationValue($relation);

        return $record instanceof Model ? $record : null;
    }

    public function categoryLabel(): ?string
    {
        return app(WorkCategoryCatalog::class)->label($this->category_code);
    }

    /** Kimden bekleniyor: personel adi, taraf adi ya da serbest metin. */
    public function waitingLabel(): ?string
    {
        return match ($this->waiting_kind) {
            WorkWaitingKind::Personnel => $this->waitingPersonnel?->full_name,
            WorkWaitingKind::Party => $this->waitingParty?->display_name ?? $this->waiting_text,
            WorkWaitingKind::Text => $this->waiting_text,
            default => null,
        };
    }

    /** Talep eden: personel adi, taraf adi ya da serbest metin. */
    public function requesterLabel(): ?string
    {
        return match ($this->requester_kind) {
            WorkWaitingKind::Personnel => $this->requesterPersonnel?->full_name,
            WorkWaitingKind::Party => $this->requesterParty?->display_name ?? $this->requester_text,
            WorkWaitingKind::Text => $this->requester_text,
            default => null,
        };
    }

    /** Bekleyen gun sayisi (yalniz Bekleniyor durumunda). */
    public function waitingDays(?Carbon $now = null): ?int
    {
        if ($this->status !== WorkItemStatus::Waiting) {
            return null;
        }

        $since = $this->waiting_since ?? $this->status_changed_at;

        if ($since === null) {
            return null;
        }

        $zone = DisplayTime::zone();
        $today = ($now ?? Carbon::now('UTC'))->copy()->timezone($zone)->startOfDay();

        return (int) max(0, $since->copy()->timezone($zone)->startOfDay()->diffInDays($today));
    }

    public function isLongWaiting(): bool
    {
        return ($this->waitingDays() ?? 0) > self::LONG_WAIT_DAYS;
    }

    /**
     * Kovadaki toplam sure (saniye); acik kartin surmekte olan dilimi eklenir.
     *
     * @param  'progress'|'waiting'|'blocked'  $bucket
     */
    public function secondsIn(string $bucket, ?Carbon $now = null): int
    {
        $stored = (int) match ($bucket) {
            'progress' => $this->progress_seconds,
            'waiting' => $this->waiting_seconds,
            'blocked' => $this->blocked_seconds,
            default => 0,
        };

        if ($this->status?->durationBucket() === $bucket && $this->status_changed_at !== null) {
            $stored += max(0, (int) $this->status_changed_at->diffInSeconds($now ?? Carbon::now('UTC'), true));
        }

        return $stored;
    }

    /** Acilistan kapanisa (ya da bugune) takvim gunu; ayni gun 1 gun sayilir. */
    public function totalDays(?Carbon $now = null): int
    {
        $zone = DisplayTime::zone();
        $start = ($this->work_at ?? $this->created_at ?? Carbon::now('UTC'))->copy()->timezone($zone)->startOfDay();
        $end = ($this->completed_at ?? $now ?? Carbon::now('UTC'))->copy()->timezone($zone)->startOfDay();

        return (int) max(1, $start->diffInDays($end, false));
    }

    /**
     * Termin sapmasi (gun): kapanan kartta kapanis - termin, acik kartta
     * bugun - termin. Termin yoksa null.
     */
    public function dueDeviation(?Carbon $now = null): ?int
    {
        if ($this->due_on === null) {
            return null;
        }

        $zone = DisplayTime::zone();
        $reference = $this->done_on ?? ($this->completed_at?->copy()->timezone($zone)) ?? ($now ?? Carbon::now('UTC'))->copy()->timezone($zone);

        return (int) Carbon::parse($this->due_on->format('Y-m-d'), $zone)->startOfDay()
            ->diffInDays(Carbon::parse($reference->format('Y-m-d'), $zone)->startOfDay(), false);
    }
}
