<?php

declare(strict_types=1);

namespace App\Query\Ui;

use App\Enums\Acquisition\OfferStatus;
use App\Enums\Approval\ApprovalRequestStatus;
use App\Enums\Document\DocumentStatus;
use App\Enums\Notification\AnnouncementPriority;
use App\Enums\Party\MeetingPlanStatus;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\PurchaseRequestStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\TenderDeadline;
use App\Models\Acquisition\TenderNotice;
use App\Models\Approval\ApprovalRequest;
use App\Models\Document\Document;
use App\Models\Notification\Announcement;
use App\Models\Party\MeetingPlan;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Document\DocumentRevision;
use App\Models\Project\Project;
use App\Models\Report\WorkItem;
use App\Models\WorkRequest\WorkRequest;
use App\Enums\Report\WorkItemStatus;
use App\Query\Acquisition\BusinessCaseQueries;
use App\Query\Document\FixedDocumentQueries;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use App\Support\Projects\ProjectNames;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * UI Deneme > Yeni pano taslagi (D-207, 12 Ekim 2026 toplanti notlari): alim,
 * yonetici ve satin alma panolarinin salt okunur sorgulari. Hicbir kayit
 * yazilmaz; sirasi panonun kurali: verilip sonuclanmayanlar ve yaklasan
 * tarihler onde, sonuclananlar arkada.
 */
final class NewDashboardQueries
{
    /** Sirket duzeyindeki standart doküman tipleri (teklif grubunun onayladigi). */
    public const STANDARD_DOCUMENT_TYPES = ['KAT', 'REF', 'KAL', 'ISG', 'DVR'];

    /** Departman sablonlarinin dokuman turu (D-208, StandardTemplateDocumentType20261014Seeder). */
    public const TEMPLATE_DOCUMENT_TYPE = 'SAB';

    /** Potansiyel is derecesi (1 en sicak): sicaklik %70+ = 1, %40+ = 2, %1+ = 3, 0 = derecesiz. */
    public static function grade(?int $heat): ?int
    {
        return match (true) {
            $heat === null || $heat <= 0 => null,
            $heat >= 70 => 1,
            $heat >= 40 => 2,
            default => 3,
        };
    }

    /**
     * Verilecek teklifler: dereceye gore (1 once), sonra en eski bekleyen once.
     *
     * @return Collection<int, Proposal>
     */
    public function proposalsToSubmit(): Collection
    {
        return $this->proposals([OfferStatus::ToBeSubmitted])
            ->sortBy([
                fn (Proposal $a, Proposal $b): int => (self::grade($a->businessCase?->heat_score) ?? 9) <=> (self::grade($b->businessCase?->heat_score) ?? 9),
                fn (Proposal $a, Proposal $b): int => $a->created_at <=> $b->created_at,
            ])
            ->values();
    }

    /**
     * Verilen, sonucu beklenen teklifler: en uzun bekleyen once (notlar:
     * "verilip sonuclanmayanlar onde gozukmeli").
     *
     * @return Collection<int, Proposal>
     */
    public function proposalsAwaitingResult(): Collection
    {
        return $this->proposals([OfferStatus::Submitted])
            ->sortBy(fn (Proposal $proposal): string => (string) ($proposal->currentVersion?->submitted_at ?? $proposal->created_at))
            ->values();
    }

    /**
     * Sonuclanan teklifler (kazandik / kaybettik): en son once, arkadaki sekme.
     *
     * @return Collection<int, Proposal>
     */
    public function proposalsConcluded(int $limit = 30): Collection
    {
        return $this->proposals([OfferStatus::Approved, OfferStatus::Lost])
            ->sortByDesc('updated_at')
            ->take($limit)
            ->values();
    }

    /**
     * Acik ihaleler: en yakin son tarih once; tarihi olmayan arkada.
     *
     * @return Collection<int, TenderNotice>
     */
    public function openTenders(): Collection
    {
        return TenderNotice::query()
            ->with(['currentVersion.deadlines', 'issuerParty:id,display_name', 'source'])
            ->where('is_draft', false)
            ->get()
            ->sortBy(fn (TenderNotice $notice): string => self::nextDeadline($notice)?->local_due_date?->format('Y-m-d') ?? '9999-12-31')
            ->values();
    }

    /**
     * Projelerin acik isleri (D-217, Yonetici paneli Proje - Operasyon seridi;
     * kullanici 15 Ekim 2026: "Projeler toogle olarak açılıp kapatılabilir
     * olmalı ve içinde de aynı arayüzde işler gelir"). Kritik ve son tarihi
     * yakin olan once.
     *
     * @return Collection<int, WorkItem>
     */
    public function openProjectWorkItems(int $limit = 300): Collection
    {
        if (! SchemaReadiness::hasBatch('B36')) {
            return collect();
        }

        return WorkItem::query()
            ->with(['project', ...self::projectScopes(), 'personnel:id,full_name'])
            ->whereNotNull('project_id')
            ->whereIn('status', WorkItemStatus::openValues())
            ->orderByDesc('is_critical')
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Onay taleplerinin projesi (D-217): konu kaydindan (satin alma siparisi ve
     * kalemi, talep, dokuman surumu). Projesiz onay listede yer almaz.
     *
     * @param  Collection<int, ApprovalRequest>  $approvals
     * @return array<int, Project> onay talebi kimligi => proje
     */
    public function approvalProjects(Collection $approvals): array
    {
        $projectIds = [];

        foreach ($approvals->groupBy('subject_type') as $type => $rows) {
            $ids = $rows->pluck('subject_id')->map(fn (mixed $id): int => (int) $id)->all();
            $map = match ((string) $type) {
                'purchase_order' => PurchaseOrder::query()->whereIn('id', $ids)->pluck('project_id', 'id')->all(),
                'purchase_need' => PurchaseNeed::query()->whereIn('id', $ids)->pluck('project_id', 'id')->all(),
                'work_request' => WorkRequest::query()->whereIn('id', $ids)->pluck('project_id', 'id')->all(),
                'document_revision' => DocumentRevision::query()
                    ->join('documents', 'documents.id', '=', 'document_revisions.document_id')
                    ->whereIn('document_revisions.id', $ids)
                    ->pluck('documents.project_id', 'document_revisions.id')
                    ->all(),
                default => [],
            };

            foreach ($rows as $approval) {
                $projectId = $map[(int) $approval->subject_id] ?? null;

                if ($projectId !== null) {
                    $projectIds[(int) $approval->getKey()] = (int) $projectId;
                }
            }
        }

        $projects = Project::query()->whereIn('id', array_values(array_unique($projectIds)) ?: [0])->get()->keyBy('id');
        $result = [];

        foreach ($projectIds as $approvalId => $projectId) {
            if ($projects->has($projectId)) {
                $result[$approvalId] = $projects->get($projectId);
            }
        }

        return $result;
    }

    /** Ihalenin bugunden sonraki ilk son tarihi; yoksa en son tarihi. */
    public static function nextDeadline(TenderNotice $notice): ?TenderDeadline
    {
        $deadlines = $notice->currentVersion?->deadlines ?? collect();
        $today = DisplayTime::today()->format('Y-m-d');

        return $deadlines->filter(fn (TenderDeadline $deadline): bool => $deadline->local_due_date?->format('Y-m-d') >= $today)
            ->sortBy('local_due_date')
            ->first()
            ?? $deadlines->sortByDesc('local_due_date')->first();
    }

    /**
     * Standart dokümanlar: tipin en yeni kaydi once.
     *
     * @return Collection<int, Document>
     */
    public function standardDocuments(): Collection
    {
        return Document::query()
            ->with(['documentType', 'currentRevision'])
            ->whereHas('documentType', fn ($type) => $type->whereIn('code', self::STANDARD_DOCUMENT_TYPES))
            ->latest('updated_at')
            ->limit(30)
            ->get();
    }

    /**
     * Dun / bugun / yarin: o gunun teklifleri, ihale son tarihleri, gorusme
     * planlari, yeni potansiyel isler ve beklenen teslimler.
     *
     * @return array<string, list<array{kind: string, at: ?Carbon, model: mixed}>>
     */
    public function days(): array
    {
        $today = DisplayTime::today();
        $buckets = [
            'yesterday' => $today->copy()->subDay(),
            'today' => $today->copy(),
            'tomorrow' => $today->copy()->addDay(),
        ];
        $from = $buckets['yesterday']->copy()->startOfDay();
        $until = $buckets['tomorrow']->copy()->endOfDay();
        $items = [];

        foreach ($this->proposals([OfferStatus::Submitted, OfferStatus::Approved, OfferStatus::Lost]) as $proposal) {
            $at = $proposal->currentVersion?->submitted_at;

            if ($at !== null && $at->copy()->timezone(DisplayTime::zone())->between($from, $until)) {
                $items[] = ['kind' => 'proposal_submitted', 'at' => $at, 'model' => $proposal];
            }
        }

        foreach ($this->proposals([OfferStatus::ToBeSubmitted]) as $proposal) {
            if ($proposal->created_at?->copy()->timezone(DisplayTime::zone())->between($from, $until)) {
                $items[] = ['kind' => 'proposal_created', 'at' => $proposal->created_at, 'model' => $proposal];
            }
        }

        TenderDeadline::query()
            ->with('version.notice')
            ->whereBetween('local_due_date', [$from->format('Y-m-d'), $until->format('Y-m-d')])
            ->get()
            ->each(function (TenderDeadline $deadline) use (&$items): void {
                $items[] = ['kind' => 'tender_deadline', 'at' => $deadline->local_due_date, 'model' => $deadline];
            });

        MeetingPlan::query()
            ->notArchived()
            ->with('party:id,display_name')
            ->whereBetween('planned_on', [$from->format('Y-m-d'), $until->format('Y-m-d')])
            ->get()
            ->each(function (MeetingPlan $plan) use (&$items): void {
                $items[] = ['kind' => 'meeting', 'at' => $plan->planned_on, 'model' => $plan];
            });

        BusinessCase::query()
            ->with('codes')
            ->where('is_draft', false)
            ->whereBetween('created_at', [$from->copy()->utc(), $until->copy()->utc()])
            ->get()
            ->each(function (BusinessCase $case) use (&$items): void {
                $items[] = ['kind' => 'case_created', 'at' => $case->created_at, 'model' => $case];
            });

        if (SchemaReadiness::hasBatch('B53')) {
            PurchaseOrder::query()
                ->notArchived()
                ->whereIn('status', [PurchaseOrderStatus::Ordered->value, PurchaseOrderStatus::InTransit->value])
                ->whereBetween('expected_delivery_on', [$from->format('Y-m-d'), $until->format('Y-m-d')])
                ->get()
                ->each(function (PurchaseOrder $order) use (&$items): void {
                    $items[] = ['kind' => 'delivery', 'at' => $order->getAttribute('expected_delivery_on'), 'model' => $order];
                });
        }

        $result = ['yesterday' => [], 'today' => [], 'tomorrow' => []];

        foreach ($items as $item) {
            $day = $item['at'] instanceof Carbon ? $item['at']->copy()->timezone(DisplayTime::zone())->format('Y-m-d') : null;

            foreach ($buckets as $key => $date) {
                if ($day === $date->format('Y-m-d')) {
                    $result[$key][] = $item;
                }
            }
        }

        return $result;
    }

    /**
     * Teklif hazirlik asamasindaki potansiyel isler (yonetici: is gelistirmede bekleyen).
     *
     * @return Collection<int, BusinessCase>
     */
    public function casesInOfferPreparation(): Collection
    {
        return BusinessCase::query()
            ->with(['codes', 'scopes', 'primaryParty:id,display_name', 'owner:id,full_name'])
            ->where('acquisition_stage', 'offer_preparation')
            ->where('is_draft', false)
            ->oldest('updated_at')
            ->get();
    }

    /**
     * Karar bekleyen onay talepleri (yonetici: proje / operasyon).
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function openApprovals(): Collection
    {
        return ApprovalRequest::query()
            ->with('requester:id,full_name')
            ->whereIn('status', [ApprovalRequestStatus::Pending->value, ApprovalRequestStatus::InProgress->value])
            ->oldest()
            ->get();
    }

    /**
     * Acik satin alma talepleri (veri girisi / bekleyen).
     *
     * @return Collection<int, PurchaseRequest>
     */
    public function openPurchaseRequests(): Collection
    {
        if (! SchemaReadiness::hasBatch('B53')) {
            return collect();
        }

        return PurchaseRequest::query()
            ->with(['requestedBy:id,full_name', 'project', ...self::projectScopes()])
            ->whereNull('archived_at')
            ->whereIn('status', [PurchaseRequestStatus::Open->value, PurchaseRequestStatus::InProgress->value])
            ->oldest()
            ->get();
    }

    /**
     * Teklif toplama asamasindaki kalemler (veri girisi).
     *
     * @return Collection<int, PurchaseNeed>
     */
    public function needsInEntry(): Collection
    {
        return PurchaseNeed::query()
            ->with(['project', ...self::projectScopes(), 'purchaseRequest'])
            ->whereIn('status', [PurchaseNeedStatus::Open->value, PurchaseNeedStatus::Quoting->value, PurchaseNeedStatus::Recommended->value])
            ->oldest()
            ->get();
    }

    /**
     * Satin almasi suren projeler: projeye gore acik kalem sayisi.
     *
     * @return Collection<int, array{project: mixed, open: int, ordered: int}>
     */
    public function projectsWithOpenPurchasing(): Collection
    {
        return PurchaseNeed::query()
            ->with('project')
            ->whereNotNull('project_id')
            ->whereNotIn('status', [PurchaseNeedStatus::Completed->value, PurchaseNeedStatus::Cancelled->value])
            ->get()
            ->groupBy('project_id')
            ->map(fn (Collection $needs): array => [
                'project' => $needs->first()?->project,
                'open' => $needs->count(),
                'ordered' => $needs->filter(fn (PurchaseNeed $need): bool => $need->status === PurchaseNeedStatus::Ordered)->count(),
            ])
            ->sortByDesc('open')
            ->values();
    }

    /**
     * Siparise donusmeyi / onayi bekleyen siparisler.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function pendingOrders(): Collection
    {
        return $this->orders([PurchaseOrderStatus::Draft, PurchaseOrderStatus::AwaitingApproval, PurchaseOrderStatus::Approved]);
    }

    /**
     * Uretim / yolda: verilmis ve teslim beklenen siparisler, en yakin teslim once.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function ordersInProduction(): Collection
    {
        return $this->orders([PurchaseOrderStatus::Ordered, PurchaseOrderStatus::InTransit])
            ->sortBy(fn (PurchaseOrder $order): string => (string) ($order->getAttribute('expected_delivery_on') ?? '9999-12-31'))
            ->values();
    }

    /**
     * Acil uyari adaylari (React pano, D-207): acil isaretli ya da tarihi
     * yaklasan acik kayitlar. Seviye ve aralik UrgencyRule ile hesaplanir.
     *
     * @return array{requests: Collection<int, PurchaseRequest>, needs: Collection<int, PurchaseNeed>, work: Collection<int, WorkItem>, deadlines: Collection<int, TenderDeadline>, approvals: Collection<int, PurchaseOrder>, deliveries: Collection<int, PurchaseOrder>}
     */
    public function urgentCandidates(): array
    {
        $soon = DisplayTime::today()->addDays(7)->format('Y-m-d');
        $b53 = SchemaReadiness::hasBatch('B53');

        return [
            'requests' => $b53 ? PurchaseRequest::query()
                ->with('requestedBy:id,full_name')
                ->whereNull('archived_at')
                ->whereIn('status', [PurchaseRequestStatus::Open->value, PurchaseRequestStatus::InProgress->value])
                ->where(fn ($query) => $query->where('is_urgent', true)->orWhere('needed_by', '<=', $soon))
                ->get() : collect(),
            'needs' => PurchaseNeed::query()
                ->with(['project', ...self::projectScopes(), 'purchaseRequest'])
                ->whereNotIn('status', [PurchaseNeedStatus::Ordered->value, PurchaseNeedStatus::Completed->value, PurchaseNeedStatus::Cancelled->value])
                ->where(fn ($query) => $query->where('is_urgent', true)->orWhere('needed_by', '<=', $soon))
                ->get(),
            'work' => SchemaReadiness::hasBatch('B36') ? WorkItem::query()
                ->with('personnel:id,full_name')
                ->whereNotIn('status', ['done', 'cancelled', 'blocked'])
                ->where(fn ($query) => $query->where('is_critical', true)->orWhere('due_on', '<=', DisplayTime::today()->addDays(3)->format('Y-m-d')))
                ->get() : collect(),
            'deadlines' => TenderDeadline::query()
                ->with('version.notice')
                ->whereBetween('local_due_date', [DisplayTime::today()->format('Y-m-d'), $soon])
                ->get(),
            'approvals' => $this->orders([PurchaseOrderStatus::AwaitingApproval]),
            'deliveries' => $this->orders([PurchaseOrderStatus::Ordered, PurchaseOrderStatus::InTransit])
                ->filter(fn (PurchaseOrder $order): bool => $order->getAttribute('expected_delivery_on') instanceof Carbon
                    && $order->getAttribute('expected_delivery_on')->format('Y-m-d') <= DisplayTime::today()->addDays(2)->format('Y-m-d'))
                ->values(),
        ];
    }

    /**
     * Acil duyurular (ustteki seritte): son 30 gunun acil oncelikli duyurulari.
     *
     * @return Collection<int, Announcement>
     */
    /**
     * Is Gelistirme durumundaki kayitlar (D-212): Yatirimci projeleri ($potential
     * false) ya da Potansiyel isler; turu Is Gelistirme listesinin sekmeleriyle
     * ayni kural (BusinessCaseQueries::withKind). Taslaklar disarida.
     *
     * @return Collection<int, BusinessCase>
     */
    public function casesInDevelopment(bool $potential): Collection
    {
        $query = BusinessCase::query()->with(['codes', 'scopes', 'primaryParty:id,display_name', 'owner:id,full_name']);

        return app(BusinessCaseQueries::class)
            ->withKind($query, $potential, true)
            ->orderByDesc('heat_score')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();
    }

    /**
     * Gorusulecekler (D-212, "Is gelistirmeye bu hafta - bu gun - bu ay
     * gorusulecekleri gosteren ... alan"): bugunden ay sonuna kadar planli
     * gorusmeler.
     *
     * @return Collection<int, MeetingPlan>
     */
    public function meetingsAhead(): Collection
    {
        $today = DisplayTime::today();
        $until = $today->copy()->endOfMonth()->max($today->copy()->endOfWeek());

        return MeetingPlan::query()
            ->notArchived()
            ->with(['party:id,display_name', 'personnel:id,full_name'])
            ->where('status', MeetingPlanStatus::Planned->value)
            ->whereBetween('planned_on', [$today->format('Y-m-d'), $until->format('Y-m-d')])
            ->orderBy('planned_on')
            ->limit(80)
            ->get();
    }

    /**
     * Departman sablonlari (D-208): SAB turundeki etkin belgeler, sahip
     * birimiyle (bos = butun departmanlar). Yuklenen belge revizyonu
     * yayimlanana kadar taslaktir (guncel revizyonu bos); taslak da sayilir
     * (15 Ekim 2026: Satin Alma'ya eklenen taslak sablon panoya dusmuyordu),
     * gosterilen revizyon Document::displayRevision().
     *
     * @return Collection<int, Document>
     */
    public function templates(): Collection
    {
        return Document::query()
            ->with(['ownerOrgUnit:id,name,code', 'currentRevision'])
            ->whereHas('documentType', fn ($type) => $type->where('code', self::TEMPLATE_DOCUMENT_TYPE))
            ->whereNotIn('status', array_map(static fn (DocumentStatus $status): string => $status->value, FixedDocumentQueries::INACTIVE_STATUSES))
            ->whereHas('revisions')
            ->orderBy('title')
            ->limit(200)
            ->get();
    }

    /**
     * Acik onay talepleri, id ile (siparisin "Onay ver" dugmesi icin).
     *
     * @param  list<int>  $ids
     * @return Collection<int, ApprovalRequest>
     */
    public function approvalRequests(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return ApprovalRequest::query()
            ->whereKey($ids)
            ->where('status', ApprovalRequestStatus::InProgress->value)
            ->get()
            ->keyBy(fn (ApprovalRequest $request): int => (int) $request->getKey());
    }

    public function urgentAnnouncements(): Collection
    {
        return Announcement::query()
            ->with('sender:id,full_name')
            ->where('priority', AnnouncementPriority::Urgent->value)
            ->where('sent_at', '>=', Carbon::now()->subDays(30))
            ->orderByDesc('sent_at')
            ->limit(3)
            ->get();
    }

    /**
     * Teklif istatistigi (alim panosunun alti).
     *
     * @return array{to_submit: int, awaiting: int, won: int, lost: int, oldest_waiting_days: ?int}
     */
    public function acquisitionStats(): array
    {
        $awaiting = $this->proposalsAwaitingResult();
        $oldest = $awaiting->first()?->currentVersion?->submitted_at;
        $counts = Proposal::query()->selectRaw('offer_status, COUNT(*) as aggregate')->groupBy('offer_status')->pluck('aggregate', 'offer_status');

        return [
            'to_submit' => (int) ($counts[OfferStatus::ToBeSubmitted->value] ?? 0),
            'awaiting' => (int) ($counts[OfferStatus::Submitted->value] ?? 0),
            'won' => (int) ($counts[OfferStatus::Approved->value] ?? 0),
            'lost' => (int) ($counts[OfferStatus::Lost->value] ?? 0),
            'oldest_waiting_days' => $oldest !== null ? (int) $oldest->diffInDays(Carbon::now()) : null,
        ];
    }

    /**
     * @param  list<OfferStatus>  $statuses
     * @return Collection<int, Proposal>
     */
    private function proposals(array $statuses): Collection
    {
        return Proposal::query()
            ->with(['currentVersion', ...(SchemaReadiness::hasBatch('B43') ? ['currentVersion.scopes'] : []), 'businessCase.codes', 'businessCase.scopes', 'businessCase.primaryParty:id,display_name', 'owner:id,full_name'])
            ->whereIn('offer_status', array_map(static fn (OfferStatus $status): string => $status->value, $statuses))
            ->get();
    }

    /**
     * @param  list<PurchaseOrderStatus>  $statuses
     * @return Collection<int, PurchaseOrder>
     */
    private function orders(array $statuses): Collection
    {
        if (! SchemaReadiness::hasBatch('B53')) {
            return collect();
        }

        return PurchaseOrder::query()
            ->notArchived()
            ->with(['project', ...self::projectScopes(), 'supplier'])
            ->whereIn('status', array_map(static fn (PurchaseOrderStatus $status): string => $status->value, $statuses))
            ->oldest()
            ->get();
    }

    /**
     * Projenin kapsamlari (B48, proje tipi rozetleri; D-222) yalniz tip ekranda
     * gosteriliyorsa yuklenir.
     *
     * @return list<string>
     */
    private static function projectScopes(): array
    {
        return ProjectNames::scopeTypesEnabled() ? ['project.scopes'] : [];
    }
}
