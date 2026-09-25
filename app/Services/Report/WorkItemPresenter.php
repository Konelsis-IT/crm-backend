<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemSource;
use App\Enums\Report\WorkItemStatus;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Resources\WorkRequests\WorkRequestResource;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Contract;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Acquisition\TenderNotice;
use App\Models\Activity\PersonnelActivity;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Models\Party\MeetingPlan;
use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Project\ProjectPhoto;
use App\Models\Project\ProjectSupplyItem;
use App\Models\Report\Report;
use App\Models\Report\WorkItem;
use App\Models\WorkRequest\WorkRequest;
use App\Query\Report\WorkItemQueries;
use App\Reports\Work\WorkCategoryCatalog;
use App\Reports\Work\WorkSuggestionCatalog;
use App\Support\ActivityLabels;
use App\Support\DisplayTime;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * Is panosu kartlarini ve onerilerini React arayuzunun bicimine cevirir
 * (B36, D-115). Ham kod ekrana gitmez: durum, kaynak, kategori ve bagli
 * kayit turu her zaman Turkce etiketiyle birlikte gonderilir.
 */
final class WorkItemPresenter
{
    public function __construct(
        private readonly WorkCategoryCatalog $categories,
        private readonly WorkSuggestionCatalog $suggestions,
        private readonly WorkItemQueries $queries,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function card(WorkItem $item, ?Personnel $viewer = null): array
    {
        $zone = DisplayTime::zone();
        $workAt = $item->work_at?->copy()->timezone($zone);
        $link = $this->link($item);
        $waiting = $this->waiting($item);
        $source = $item->source ?? WorkItemSource::Manual;

        return [
            'id' => (int) $item->getKey(),
            'title' => (string) $item->title,
            'status' => $item->status?->value,
            'status_label' => $item->status?->getLabel(),
            'is_critical' => (bool) $item->is_critical,
            'work_at' => $item->work_at?->toIso8601String(),
            'work_on' => $item->work_on?->format('Y-m-d'),
            'time' => $workAt?->format('H:i'),
            'due_on' => $item->due_on?->format('Y-m-d'),
            'done_on' => $item->done_on?->format('Y-m-d'),
            'personnel' => $item->personnel !== null ? $this->person($item->personnel) : null,
            'unit' => $item->orgUnit !== null ? ['id' => (int) $item->orgUnit->getKey(), 'name' => (string) $item->orgUnit->name, 'code' => (string) $item->orgUnit->code] : null,
            'project' => $item->project !== null ? ['id' => (int) $item->project->getKey(), 'name' => (string) $item->project->name] : null,
            'parent' => $item->parent !== null ? ['id' => (int) $item->parent->getKey(), 'title' => (string) $item->parent->title] : null,
            'children' => (int) ($item->getAttribute('children_count') ?? 0),
            'category' => $item->category_code,
            'category_label' => $item->categoryLabel(),
            'waiting' => $waiting,
            'requester' => $this->requester($item),
            'link' => $link,
            'source' => $source->value,
            'source_label' => $source->getLabel(),
            'source_tag' => $source === WorkItemSource::Automatic
                ? $source->getLabel().($link !== null ? ' · '.$link['kind_label'] : '')
                : null,
            'hours' => $item->work_hours !== null ? (float) $item->work_hours : null,
            'note' => $item->note,
            'sort_order' => (int) $item->sort_order,
            'row_version' => (int) $item->row_version,
            'url' => $this->safe(fn (): string => WorkItemResource::getUrl('view', ['record' => $item])),
            'can' => [
                'update' => $viewer !== null && Gate::forUser($viewer)->allows('update', $item),
                'delete' => $viewer !== null && Gate::forUser($viewer)->allows('delete', $item),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, initials: string}
     */
    public function person(Personnel $person): array
    {
        return [
            'id' => (int) $person->getKey(),
            'name' => (string) $person->full_name,
            'initials' => self::initials((string) $person->full_name),
        ];
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';

        return mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function waiting(WorkItem $item): ?array
    {
        if ($item->waiting_kind === null) {
            return null;
        }

        $days = $item->waitingDays();

        return [
            'kind' => $item->waiting_kind->value,
            'kind_label' => $item->waiting_kind->getLabel(),
            'label' => $item->waitingLabel(),
            'personnel_id' => $item->waiting_personnel_id !== null ? (int) $item->waiting_personnel_id : null,
            'party_id' => $item->waiting_party_id !== null ? (int) $item->waiting_party_id : null,
            'text' => $item->waiting_text,
            'days' => $days,
            'late' => $days !== null && $days > WorkItem::LONG_WAIT_DAYS,
        ];
    }

    /**
     * Talep eden (B37, D-118): kim istedi. Bos ise kisinin kendi isidir.
     *
     * @return array<string, mixed>|null
     */
    public function requester(WorkItem $item): ?array
    {
        if ($item->requester_kind === null) {
            return null;
        }

        return [
            'kind' => $item->requester_kind->value,
            'kind_label' => $item->requester_kind->getLabel(),
            'label' => $item->requesterLabel(),
            'personnel_id' => $item->requester_personnel_id !== null ? (int) $item->requester_personnel_id : null,
            'party_id' => $item->requester_party_id !== null ? (int) $item->requester_party_id : null,
            'text' => $item->requester_text,
        ];
    }

    /**
     * @return array{kind: string, kind_label: string, id: int, no: string|null, label: string, url: string|null}|null
     */
    public function link(WorkItem $item): ?array
    {
        $kind = $item->link_kind ?? WorkItemLinkKind::None;
        $record = $item->linkedRecord();

        if ($kind === WorkItemLinkKind::None || $record === null) {
            return null;
        }

        [$no, $label] = $this->linkText($kind, $record);

        return [
            'kind' => $kind->value,
            'kind_label' => $kind->getLabel(),
            'id' => (int) $record->getKey(),
            'no' => $no,
            'label' => $label,
            'url' => $this->recordUrl($kind, $record),
        ];
    }

    /**
     * Bagli kaydin numarasi ve adi.
     *
     * @return array{0: string|null, 1: string}
     */
    public function linkText(WorkItemLinkKind $kind, Model $record): array
    {
        return match (true) {
            $record instanceof Proposal => [$record->proposal_no, (string) $record->title],
            $record instanceof BusinessCase => [null, (string) $record->title],
            $record instanceof TenderNotice => [$record->external_notice_id, (string) $record->title],
            $record instanceof Document => [$record->document_no, (string) $record->title],
            $record instanceof ProjectSupplyItem => [$record->item_code, (string) $record->name],
            $record instanceof WorkRequest => [$record->request_no, (string) $record->title],
            $record instanceof MeetingPlan => [$record->planned_on?->format('d.m.Y'), trim((string) ($record->party?->display_name ?? '').($record->subject ? ' · '.$record->subject : ''), ' ·')],
            default => [null, (string) $kind->getLabel()],
        };
    }

    public function recordUrl(WorkItemLinkKind $kind, Model $record): ?string
    {
        return $this->safe(fn (): ?string => match ($kind) {
            WorkItemLinkKind::BusinessCase => BusinessCaseResource::getUrl('view', ['record' => $record]),
            WorkItemLinkKind::Proposal => ProposalResource::getUrl('view', ['record' => $record]),
            WorkItemLinkKind::TenderNotice => TenderNoticeResource::getUrl('view', ['record' => $record]),
            WorkItemLinkKind::Document => DocumentResource::getUrl('view', ['record' => $record]),
            WorkItemLinkKind::SupplyItem => $record instanceof ProjectSupplyItem && $record->project_id !== null
                ? ProjectResource::getUrl('view', ['record' => $record->project_id])
                : null,
            WorkItemLinkKind::WorkRequest => WorkRequestResource::getUrl('view', ['record' => $record]),
            WorkItemLinkKind::MeetingPlan => MeetingPlanResource::getUrl('view', ['record' => $record]),
            WorkItemLinkKind::None => null,
        });
    }

    /**
     * Oneri satiri + "Kart yap" penceresinin on dolu degerleri.
     *
     * @param  array<string, array<int, Model>>  $subjects
     * @return array<string, mixed>|null  konu kaydi silinmisse null
     */
    public function suggestion(PersonnelActivity $activity, array $subjects, ?string $unitCode, bool $dismissed = false): ?array
    {
        $code = (string) $activity->action_code;
        $subject = $subjects[(string) $activity->subject_type][(int) $activity->subject_id] ?? null;

        if (! $subject instanceof Model) {
            return null;
        }

        // Panonun kendi urettigi raporlar (gunluk/haftalik calisma, kontrol,
        // koordinasyon) oneri olmaz; yalniz elle yazilan rapor kart onerir.
        if ($subject instanceof Report && $subject->template()?->isManualEntry() !== true) {
            return null;
        }

        $resolved = $this->resolveSubject($code, $subject);
        $zone = DisplayTime::zone();
        $at = $activity->occurred_at?->copy()->timezone($zone);
        $today = WorkItemQueries::today();
        $kind = $resolved['link_kind'];

        return [
            'id' => (int) $activity->getKey(),
            'action' => $code,
            'label' => $this->suggestions->label($code),
            'subject' => $resolved['name'],
            'mono' => $resolved['mono'],
            'at' => $activity->occurred_at?->toIso8601String(),
            'time' => $at === null ? null : ($at->isSameDay($today) ? $at->format('H:i') : $at->format('d.m H:i')),
            'module' => $this->suggestions->moduleLabel($code),
            'dismissed' => $dismissed,
            'defaults' => [
                'title' => Str::limit($this->suggestions->titlePrefix($code).': '.$resolved['name'], 200, ''),
                'project_id' => $resolved['project_id'],
                'project_name' => $resolved['project_name'],
                'category' => $this->suggestions->category($code, $unitCode, $this->categories),
                'status' => 'done',
                'work_on' => $at?->format('Y-m-d'),
                'time' => $at?->format('H:i'),
                'link' => $kind !== WorkItemLinkKind::None && $resolved['link_id'] !== null ? [
                    'kind' => $kind->value,
                    'kind_label' => $kind->getLabel(),
                    'id' => $resolved['link_id'],
                    'no' => $resolved['link_no'],
                    'label' => $resolved['link_label'],
                ] : null,
                'source_label' => __('work_item.values.automatic_source', ['module' => $this->suggestions->moduleLabel($code)]),
            ],
            // Oneri ayrintisi penceresi (24 Eylul 2026 kullanici istegi: kisi
            // ise donusturup donusturmeyecegine ayrintiyi gorerek karar versin).
            // Oneri kisinin kendi hareketidir; kayit sayfasi ayrica yetki denetler.
            'detail' => [
                'occurred' => $at?->format('d.m.Y H:i'),
                'record_no' => $resolved['link_no'],
                'record_label' => $resolved['link_label'] ?? $resolved['name'],
                'record_kind' => $kind !== WorkItemLinkKind::None ? $kind->getLabel() : $this->suggestions->moduleLabel($code),
                'record_status' => $resolved['status'],
                'record_url' => $resolved['url'],
                'project' => $resolved['project_name'],
                'changes' => array_map(
                    fn (string $line): string => Str::limit($line, 240, '…'),
                    array_slice(ActivityLabels::changeLines(is_array($activity->changes) ? $activity->changes : null), 0, 12),
                ),
            ],
        ];
    }

    /**
     * Hareketin konusundan kart bilgisi: gorunen ad, bagli kayit, proje.
     *
     * @return array{name: string, mono: bool, link_kind: WorkItemLinkKind, link_id: int|null, link_no: string|null, link_label: string|null, project_id: int|null, project_name: string|null}
     */
    public function resolveSubject(string $actionCode, Model $subject): array
    {
        $kind = $this->suggestions->linkKind($actionCode);
        $name = '';
        $mono = false;
        $link = null;
        $project = null;

        switch (true) {
            case $subject instanceof Proposal:
                $name = (string) $subject->title;
                $link = $subject;
                $project = $subject->businessCase?->project;
                break;
            case $subject instanceof ProposalVersion:
                $name = (string) ($subject->proposal?->title ?? '');
                $link = $subject->proposal;
                $project = $subject->proposal?->businessCase?->project;
                break;
            case $subject instanceof BusinessCase:
                $name = (string) $subject->title;
                $link = $subject;
                $project = $subject->project;
                break;
            case $subject instanceof TenderNotice:
                $name = (string) $subject->title;
                $link = $subject;
                break;
            case $subject instanceof Document:
                $name = (string) $subject->title;
                $link = $subject;
                $project = $subject->project_id !== null ? Project::query()->find($subject->project_id, ['id', 'name']) : null;
                break;
            case $subject instanceof DocumentRevision:
                $name = (string) ($subject->document?->title ?? '');
                $link = $subject->document;
                $project = $subject->document?->project_id !== null ? Project::query()->find($subject->document->project_id, ['id', 'name']) : null;
                break;
            case $subject instanceof ProjectSupplyItem:
                $name = (string) $subject->name;
                $link = $subject;
                $project = $subject->project;
                break;
            case $subject instanceof WorkRequest:
                $name = (string) $subject->request_no;
                $mono = true;
                $link = $subject;
                $project = $subject->project_id !== null ? Project::query()->find($subject->project_id, ['id', 'name']) : null;
                break;
            case $subject instanceof MeetingPlan:
                $name = (string) ($subject->party?->display_name ?? $subject->subject ?? '');
                $link = $subject;
                break;
            case $subject instanceof PartyMeetingNote:
                $name = (string) ($subject->party?->display_name ?? '');
                $link = $this->queries->meetingPlanForNote((int) $subject->getKey());
                break;
            case $subject instanceof Report:
                $name = (string) ($subject->title ?? $subject->report_no);
                break;
            case $subject instanceof Contract:
                $name = (string) $subject->contract_no;
                $mono = true;
                break;
            case $subject instanceof Project:
                $name = (string) $subject->name;
                $project = $subject;
                break;
            case $subject instanceof ProjectPhoto:
                $name = (string) ($subject->project?->name ?? '');
                $project = $subject->project;
                break;
        }

        $linkNo = null;
        $linkLabel = null;
        $url = null;

        if ($link instanceof Model && $kind !== WorkItemLinkKind::None) {
            [$linkNo, $linkLabel] = $this->linkText($kind, $link);
            $url = $this->recordUrl($kind, $link);
        }

        // Bagli kaydi olmayan konular: kaydin kendi sayfasi (oneri ayrintisi).
        $url ??= $this->safe(fn (): ?string => match (true) {
            $subject instanceof Report => ReportResource::getUrl('view', ['record' => $subject]),
            $subject instanceof Project => ProjectResource::getUrl('view', ['record' => $subject]),
            $subject instanceof ProjectPhoto && $subject->project_id !== null => ProjectResource::getUrl('view', ['record' => $subject->project_id]),
            $subject instanceof Contract => ContractResource::getUrl('view', ['record' => $subject]),
            default => null,
        });

        return [
            'name' => $name !== '' ? $name : (string) __('work_item.values.untitled'),
            'mono' => $mono,
            'link_kind' => $link instanceof Model ? $kind : WorkItemLinkKind::None,
            'link_id' => $link instanceof Model ? (int) $link->getKey() : null,
            'link_no' => $linkNo,
            'link_label' => $linkLabel,
            'project_id' => $project instanceof Project ? (int) $project->getKey() : null,
            'project_name' => $project instanceof Project ? (string) $project->name : null,
            'url' => $url,
            'status' => $this->statusLabel($link instanceof Model ? $link : $subject),
        ];
    }

    /** Kaydin durumu (etiketli durum alani varsa). */
    private function statusLabel(Model $record): ?string
    {
        try {
            $status = $record->getAttribute('status');
        } catch (Throwable) {
            return null;
        }

        return $status instanceof HasLabel ? (string) $status->getLabel() : null;
    }

    /**
     * "Gunu kapat" penceresi: gunun kartlari, sayilar, engeller ve yarin plani on dolu.
     *
     * @return array<string, mixed>
     */
    public function dayPreview(Personnel $me, Carbon $day): array
    {
        $cards = $this->queries->dayCards((int) $me->getKey(), $day);
        $report = $this->queries->dailyReport((int) $me->getKey(), $day);
        $worked = $cards->filter(fn (WorkItem $item): bool => $item->status !== WorkItemStatus::Planned);
        $planned = $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Planned);

        return [
            'date' => $day->format('Y-m-d'),
            'label' => self::dayLabel($day),
            'cards' => $this->ordered($worked)->map(fn (WorkItem $item): array => $this->card($item, $me))->values()->all(),
            'plan' => $planned->values()->map(fn (WorkItem $item): array => $this->card($item, $me))->all(),
            'kpis' => [
                'done' => $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Done)->count(),
                'in_progress' => $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::InProgress)->count(),
                'waiting' => $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Waiting)->count(),
                'blocked' => $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Blocked)->count(),
                'hours' => round((float) $worked->sum(fn (WorkItem $item): float => (float) ($item->work_hours ?? 0)), 2),
            ],
            'blockers' => $this->blockers($cards),
            'carry' => $cards->filter(fn (WorkItem $item): bool => $item->status?->isOpen() ?? false)->count(),
            'report' => $this->reportInfo($report),
            'draft' => $report !== null && $report->status->isEditable() ? [
                'summary' => $report->answer('summary'),
                'blockers' => $report->answer('blockers'),
            ] : null,
        ];
    }

    /**
     * "Haftayi kapat" penceresi: haftanin kartlari (istemci projeye / kategoriye
     * gore gruplar), sayilar, gelecek hafta plani ve inceleyecek amir.
     *
     * @return array<string, mixed>
     */
    public function weekPreview(Personnel $me, Carbon $day): array
    {
        $start = WorkItemQueries::weekStart($day);
        $end = $start->copy()->addDays(6);
        $cards = $this->queries->periodCards((int) $me->getKey(), $start, $end);
        $report = $this->queries->weeklyReport((int) $me->getKey(), $start);
        $reviewer = $me->currentManager() ?? $me->orgUnit?->manager;

        if ($reviewer instanceof Personnel && $reviewer->is($me)) {
            $reviewer = null;
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'label' => self::weekLabel($start),
            'week' => (int) $start->isoWeek(),
            'cards' => $this->ordered($cards)->map(fn (WorkItem $item): array => $this->card($item, $me))->values()->all(),
            'plan' => $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Planned)->values()->map(fn (WorkItem $item): array => $this->card($item, $me))->all(),
            'kpis' => [
                'done' => $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Done)->count(),
                'open' => $cards->filter(fn (WorkItem $item): bool => $item->status?->isOpen() ?? false)->count(),
                'waiting' => $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Waiting)->count(),
                'hours' => round((float) $cards->sum(fn (WorkItem $item): float => (float) ($item->work_hours ?? 0)), 2),
                'closed_days' => $this->queries->closedDayCount((int) $me->getKey(), $start, $start->copy()->addDays(4)),
                'workdays' => 5,
            ],
            'blockers' => $this->blockers($cards),
            'reviewer' => $reviewer instanceof Personnel ? (string) $reviewer->full_name : null,
            'report' => $this->reportInfo($report),
            'draft' => $report !== null && $report->status->isEditable() ? [
                'summary' => $report->answer('summary'),
                'achievements' => $report->answer('achievements'),
                'blockers' => $report->answer('blockers'),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function reportInfo(?Report $report): ?array
    {
        if (! $report instanceof Report) {
            return null;
        }

        return [
            'id' => (int) $report->getKey(),
            'no' => (string) $report->report_no,
            'status' => $report->status->value,
            'status_label' => $report->status->getLabel(),
            'editable' => $report->status->isEditable(),
            'reviewer' => $report->reviewer?->full_name,
            'url' => $this->safe(fn (): string => ReportResource::getUrl('view', ['record' => $report])),
        ];
    }

    /**
     * Engeller on dolu: uzun bekleyen kartlar. Iptal edilen is (eski
     * "Engellendi", 24 Eylul 2026) engel sayilmaz.
     *
     * @param  Collection<int, WorkItem>  $cards
     */
    private function blockers(Collection $cards): ?string
    {
        $lines = [];

        foreach ($cards as $item) {
            if ($item->status === WorkItemStatus::Waiting && $item->isLongWaiting()) {
                $lines[] = __('work_item.values.blocker_waiting', ['title' => $item->title, 'days' => (int) $item->waitingDays(), 'who' => (string) ($item->waitingLabel() ?? '-')]);
            }
        }

        return $lines === [] ? null : implode(' ', $lines);
    }

    /**
     * Durum sirasi (Tamamlandi, Devam ediyor, Bekleniyor, Engellendi, Planlandi) + sutun sirasi.
     *
     * @param  Collection<int, WorkItem>  $cards
     * @return Collection<int, WorkItem>
     */
    private function ordered(Collection $cards): Collection
    {
        $order = ['done' => 0, 'in_progress' => 1, 'waiting' => 2, 'blocked' => 3, 'planned' => 4];

        return $cards->sortBy(fn (WorkItem $item): string => sprintf('%d-%05d-%010d', $order[$item->status?->value] ?? 9, (int) $item->sort_order, (int) $item->getKey()))->values();
    }

    /** Kurum saatinde "Pazartesi 14.09.2026". */
    public static function dayLabel(Carbon $day): string
    {
        return $day->copy()->locale(app()->getLocale())->translatedFormat('l').' '.$day->format('d.m.Y');
    }

    /** "07.09 – 12.09.2026" (pazartesi - cumartesi). */
    public static function weekLabel(Carbon $start): string
    {
        return $start->format('d.m').' – '.$start->copy()->addDays(5)->format('d.m.Y');
    }

    private function safe(callable $resolve): ?string
    {
        try {
            $value = $resolve();

            return is_string($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
