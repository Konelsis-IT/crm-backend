<?php

declare(strict_types=1);

namespace App\Models\Report;

use App\Enums\Report\WorkItemLinkKind;
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
use App\Models\Project\ProjectSupplyItem;
use App\Models\WorkRequest\WorkRequest;
use App\Policies\WorkItemPolicy;
use App\Reports\Work\WorkCategoryCatalog;
use App\Support\DisplayTime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    'status_changed_at', 'started_at', 'completed_at', 'progress_seconds', 'waiting_seconds', 'blocked_seconds',
])]
#[UsePolicy(WorkItemPolicy::class)]
class WorkItem extends Model
{
    use HasAuditColumns;

    /** Bu kadar gunden uzun bekleyen kart "uzun bekleyen" sayilir. */
    public const LONG_WAIT_DAYS = 7;

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
            'work_at' => 'datetime',
            'work_on' => 'date',
            'due_on' => 'date',
            'done_on' => 'date',
            'waiting_since' => 'datetime',
            'status_changed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
