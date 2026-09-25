<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\Report\WorkItemLinkKind;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Enums\Report\WorkItemSource;
use App\Enums\Report\WorkItemStatus;
use App\Enums\Report\WorkWaitingKind;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\RecordNotFoundException;
use App\Exceptions\Report\AssigneeNotAllowedException;
use App\Exceptions\Report\DayAlreadyClosedException;
use App\Exceptions\Report\InvalidParentException;
use App\Exceptions\Report\LinkedRecordNotFoundException;
use App\Exceptions\Report\SuggestionUnavailableException;
use App\Exceptions\Report\WaitingSourceRequiredException;
use App\Exceptions\Report\WeekAlreadyClosedException;
use App\Exceptions\Report\WeekSummaryRequiredException;
use App\Exceptions\StaleRecordException;
use App\Models\Activity\PersonnelActivity;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Models\Report\ReportItem;
use App\Models\Report\WorkItem;
use App\Models\Report\WorkSuggestionDismissal;
use App\Query\Report\ReportQueries;
use App\Query\Report\WorkItemQueries;
use App\Reports\Templates\CoordinationBoardReportTemplate;
use App\Reports\Templates\DailyWorkReportTemplate;
use App\Reports\Templates\WeeklyWorkReportTemplate;
use App\Reports\Work\WorkCategoryCatalog;
use App\Services\AbstractService;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Notification\PanelNotifier;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\ActivityLabels;
use App\Support\DisplayTime;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * Is panosu karti (B36, D-115): elle ve oneriden kart, durum degisikligi
 * (surukle-birak), sutun ici sira, kritik bayragi, oneri yoksayma, gunu /
 * haftayi kapat (panoyu rapora dondurma) ve Yonetim panosu dondurmasi.
 *
 * Durum gecmisi Personel Hareketleri'ne yazilir (`work_item.status_changed`);
 * her gecis bir onceki durumda gecen sureyi ilgili kovaya ekler
 * (`progress_seconds`, `waiting_seconds`, `blocked_seconds`). Sure raporu
 * ve analiz bu izdusumu okur; sureler elle girilmez.
 */
final class WorkItemService extends AbstractService
{
    protected string $orderBy = 'work_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly WorkItemQueries $queries,
        private readonly ReportQueries $reportQueries,
        private readonly WorkCategoryCatalog $categories,
        private readonly WorkItemPresenter $presenter,
        private readonly ReportService $reports,
        private readonly PanelNotifier $notifier,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Yeni kart. Varsayilanlar: sorumlu = yazan, departman = sorumlunun
     * birimi, tarih = su an, durum = Planlandi. Ileri tarihli kart Planlandi
     * sayilir. Kapatilmis bir gune yazilan kart o gunun raporuna "sonradan
     * eklendi" olarak eklenir.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $me = $this->actorPersonnel();
        $attributes = $this->normalize($data, null, $me);
        $now = Carbon::now('UTC');
        $status = $this->initialStatus($data, $attributes);
        $this->assertWaiting($status, $attributes);

        return $this->transactions->run(function () use ($attributes, $status, $now): WorkItem {
            $workAt = $attributes['work_at'];
            $since = $workAt->lessThan($now) ? $workAt->copy() : $now->copy();

            /** @var WorkItem $item */
            $item = $this->newModel()->newInstance();
            $item->fill([
                ...$attributes,
                'status' => $status->value,
                'status_changed_at' => $since,
                'started_at' => $status === WorkItemStatus::InProgress ? $since : null,
                // Kapali durum: Tamamlandi ya da Iptal (eski "Engellendi").
                'completed_at' => ! $status->isOpen() ? $since : null,
                'done_on' => ! $status->isOpen() ? $attributes['work_on'] : null,
                'waiting_since' => $status === WorkItemStatus::Waiting ? $since : null,
                'sort_order' => 0,
            ]);
            $item->save();

            $this->recordActivity($item, 'created', $this->summary($item));
            $this->notifyAwaited($item, null);
            $this->appendToClosedDay($item);

            return $item;
        });
    }

    /**
     * Kart duzenleme (pencere, Filament formu). Durum degisirse gecis kurali
     * uygulanir (sure kovasi, tamamlanma gunu, bekleme baslangici).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        $me = $this->actorPersonnel();

        return $this->transactions->run(function () use ($record, $data, $me): WorkItem {
            /** @var WorkItem $item */
            $item = $this->lockForUpdate($record);
            $before = $this->summary($item);
            $waitingBefore = $item->waiting_personnel_id !== null ? (int) $item->waiting_personnel_id : null;
            $previousDay = $item->work_on?->format('Y-m-d');
            $attributes = $this->normalize($data, $item, $me);
            $target = array_key_exists('status', $data) && filled($data['status'])
                ? (WorkItemStatus::tryFrom((string) ($data['status'] instanceof WorkItemStatus ? $data['status']->value : $data['status'])) ?? $item->status)
                : $item->status;

            if ($attributes['work_on'] > WorkItemQueries::today()->format('Y-m-d') && $target->isOpen()) {
                $target = WorkItemStatus::Planned;
            }

            $this->assertWaiting($target, [...$item->getAttributes(), ...$attributes]);

            if (array_key_exists('row_version', $data) && filled($data['row_version']) && (int) $data['row_version'] !== (int) $item->row_version) {
                throw StaleRecordException::make();
            }

            $item->fill($attributes);
            $this->transition($item, $target, Carbon::now('UTC'));
            $this->saveWithoutVersion($item);
            // Ozet yeni proje/ana is/sorumlu adlarini okusun.
            $item->unsetRelations();

            $this->notifyAwaited($item, $waitingBefore);
            $changes = $this->diff($before, $this->summary($item));

            if ($changes !== []) {
                $this->recordActivity($item, 'updated', $changes);
            }

            if ($previousDay !== $item->work_on?->format('Y-m-d')) {
                $this->appendToClosedDay($item);
            }

            return $item;
        });
    }

    /**
     * Durum degisikligi (surukle-birak, kart menusu, toplu islem).
     * Bekleniyor: kimden beklendigi zorunlu. Tamamlandi: harcanan saat istege bagli.
     *
     * @param  array<string, mixed>  $extra  waiting_kind, waiting_personnel_id, waiting_party_id, waiting_text, work_hours, note
     */
    public function changeStatus(Model|int|string $record, string $status, array $extra = []): WorkItem
    {
        $target = WorkItemStatus::tryFrom($status) ?? throw RecordNotFoundException::make();

        return $this->transactions->run(function () use ($record, $target, $extra): WorkItem {
            /** @var WorkItem $item */
            $item = $this->lockForUpdate($record);
            $from = $item->status;
            $before = $item->waiting_personnel_id !== null ? (int) $item->waiting_personnel_id : null;

            if ($target === WorkItemStatus::Waiting) {
                $item->fill($this->waitingAttributes($extra, $item));
            }

            if (array_key_exists('work_hours', $extra) && $extra['work_hours'] !== null && $extra['work_hours'] !== '') {
                $item->work_hours = round(min(999, max(0, (float) $extra['work_hours'])), 2);
            }

            if (filled($extra['note'] ?? null)) {
                $note = trim((string) $extra['note']);
                $item->note = filled($item->note) ? $item->note."\n".$note : $note;
            }

            $this->assertWaiting($target, $item->getAttributes());
            $changed = $this->transition($item, $target, Carbon::now('UTC'));

            if ($changed) {
                $item->sort_order = 0;
            }

            $this->saveWithoutVersion($item);

            $this->notifyAwaited($item, $before);

            if ($changed || $item->wasChanged()) {
                $this->recordActivity($item, 'status_changed', array_filter([
                    'durum' => ['onceki' => $from?->getLabel(), 'yeni' => $target->getLabel()],
                    'kimden_bekleniyor' => $target === WorkItemStatus::Waiting ? $item->waitingLabel() : null,
                    'saat' => $item->wasChanged('work_hours') ? $item->work_hours : null,
                    'not' => filled($extra['note'] ?? null) ? trim((string) $extra['note']) : null,
                ], static fn ($value): bool => $value !== null && $value !== ''));
            }

            return $item;
        });
    }

    /**
     * Sutun ici sira: verilen sirayla 1..n. Yalniz tasinan kart icin hareket yazilir.
     *
     * @param  list<int>  $ids
     */
    public function reorder(string $status, array $ids, ?int $movedId = null): void
    {
        $status = WorkItemStatus::tryFrom($status) ?? throw RecordNotFoundException::make();
        $me = $this->actorPersonnel();

        $this->transactions->run(function () use ($status, $ids, $movedId, $me): void {
            $position = 0;

            foreach ($ids as $id) {
                $position++;
                /** @var WorkItem|null $item */
                $item = WorkItem::query()->lockForUpdate()->find((int) $id);

                if (! $item instanceof WorkItem || $item->status !== $status || ! Gate::forUser($me)->allows('update', $item)) {
                    continue;
                }

                if ((int) $item->sort_order === $position) {
                    continue;
                }

                $item->sort_order = $position;
                $this->saveWithoutVersion($item);

                if ($movedId !== null && (int) $item->getKey() === $movedId) {
                    $this->recordActivity($item, 'reordered', ['sira' => $position, 'durum' => $status->getLabel()]);
                }
            }
        });
    }

    public function setCritical(Model|int|string $record, bool $critical): WorkItem
    {
        return $this->transactions->run(function () use ($record, $critical): WorkItem {
            /** @var WorkItem $item */
            $item = $this->lockForUpdate($record);

            if ((bool) $item->is_critical === $critical) {
                return $item;
            }

            $item->is_critical = $critical;
            $this->saveWithoutVersion($item);
            $this->recordActivity($item, 'updated', ['kritik' => ['onceki' => ! $critical, 'yeni' => $critical]]);

            return $item;
        });
    }

    /**
     * "Kart yap": kisinin kendi hareketi, bagli kayit ve kaynak kilitli bir
     * otomatik karta doner; ad, proje, kategori, durum ve saat duzenlenir.
     *
     * @param  array<string, mixed>  $data
     */
    public function fromSuggestion(int $activityId, array $data): WorkItem
    {
        $me = $this->actorPersonnel();
        $activity = $this->queries->suggestionActivity($me, $activityId);

        if (! $activity instanceof PersonnelActivity || $this->queries->isConverted($activityId)) {
            throw SuggestionUnavailableException::make();
        }

        $subjects = $this->queries->suggestionSubjects([$activity]);
        $suggestion = $this->presenter->suggestion($activity, $subjects, $me->orgUnit?->code);

        if ($suggestion === null) {
            throw SuggestionUnavailableException::make();
        }

        $defaults = $suggestion['defaults'];
        $link = $defaults['link'] ?? null;
        $status = in_array($data['status'] ?? null, [WorkItemStatus::Planned->value, WorkItemStatus::InProgress->value, WorkItemStatus::Done->value], true)
            ? (string) $data['status']
            : WorkItemStatus::Done->value;

        return $this->transactions->run(function () use ($me, $activityId, $data, $defaults, $link, $status): WorkItem {
            // Yoksayildiktan sonra karta donerse yoksayma kalkar.
            $this->queries->dismissal((int) $me->getKey(), $activityId)?->delete();

            /** @var WorkItem $item */
            $item = $this->create([
                'title' => filled($data['title'] ?? null) ? $data['title'] : $defaults['title'],
                'project_id' => array_key_exists('project_id', $data) ? $data['project_id'] : $defaults['project_id'],
                'category_code' => array_key_exists('category_code', $data) ? $data['category_code'] : $defaults['category'],
                'status' => $status,
                'work_on' => $data['work_on'] ?? $defaults['work_on'],
                'work_time' => $data['work_time'] ?? $defaults['time'],
                'work_hours' => $data['work_hours'] ?? null,
                'link_kind' => $link['kind'] ?? WorkItemLinkKind::None->value,
                'link_id' => $link['id'] ?? null,
                'source' => WorkItemSource::Automatic->value,
                'source_activity_id' => $activityId,
            ]);

            return $item;
        });
    }

    /** Oneriyi yoksay: hareket silinmez; yalniz panoya kart olarak onerilmez. */
    public function dismissSuggestion(int $activityId): void
    {
        $me = $this->actorPersonnel();
        $activity = $this->queries->suggestionActivity($me, $activityId);

        if (! $activity instanceof PersonnelActivity || $this->queries->isConverted($activityId)) {
            throw SuggestionUnavailableException::make();
        }

        $this->transactions->run(function () use ($me, $activity): void {
            if ($this->queries->dismissal((int) $me->getKey(), (int) $activity->getKey()) !== null) {
                return;
            }

            $dismissal = WorkSuggestionDismissal::query()->create([
                'personnel_id' => (int) $me->getKey(),
                'personnel_activity_id' => (int) $activity->getKey(),
            ]);

            $this->activities->record(new ActivityInput(
                subjectType: 'work_suggestion_dismissal',
                subjectId: (int) $dismissal->getKey(),
                actionCode: 'work_suggestion_dismissal.created',
                changes: ['oneri' => ActivityLabels::action((string) $activity->action_code)],
            ));
        });
    }

    /** Yoksaymayi geri al (bildirimdeki "Geri al" ya da Yoksayilanlar listesi). */
    public function restoreSuggestion(int $activityId): void
    {
        $me = $this->actorPersonnel();

        $this->transactions->run(function () use ($me, $activityId): void {
            $dismissal = $this->queries->dismissal((int) $me->getKey(), $activityId);

            if (! $dismissal instanceof WorkSuggestionDismissal) {
                return;
            }

            $this->activities->record(new ActivityInput(
                subjectType: 'work_suggestion_dismissal',
                subjectId: (int) $dismissal->getKey(),
                actionCode: 'work_suggestion_dismissal.deleted',
                changes: null,
            ));

            $dismissal->delete();
        });
    }

    /**
     * Gunu kapat: gunun kartlari donmus kalem olur; ozet bossa kart
     * basliklarindan uretilir; yarin plani Planlandi kartlarindan gelir ve
     * eklenen satirlar ertesi is gununun panosuna kart olarak duser.
     * Gunluk rapor incelemesizdir; gonderildikten sonra gun kapanir.
     *
     * @param  array{summary?: string|null, blockers?: string|null, plan?: list<string>, extra_plan?: list<string>}  $data
     */
    public function closeDay(string $date, array $data, bool $submit): Report
    {
        $me = $this->actorPersonnel();
        $day = WorkItemQueries::day($date);
        $existing = $this->queries->dailyReport((int) $me->getKey(), $day);

        if ($existing instanceof Report && ! $existing->status->isEditable()) {
            throw DayAlreadyClosedException::make(['no' => (string) $existing->report_no]);
        }

        $cards = $this->queries->dayCards((int) $me->getKey(), $day);

        return $this->transactions->run(function () use ($me, $day, $existing, $cards, $data, $submit): Report {
            $extra = $this->planLines($data['extra_plan'] ?? []);
            $plan = [...$this->planLines($data['plan'] ?? []), ...$extra];
            $summary = trim((string) ($data['summary'] ?? ''));

            if ($summary === '') {
                $summary = $this->generatedSummary($cards);
            }

            $payload = [
                'template_code' => DailyWorkReportTemplate::CODE,
                'period_start' => $day->format('Y-m-d'),
                'period_end' => $day->format('Y-m-d'),
                'payload' => [
                    'summary' => $summary,
                    'blockers' => filled($data['blockers'] ?? null) ? trim((string) $data['blockers']) : null,
                    'tomorrow_plan' => $plan === [] ? null : implode("\n", array_map(static fn (string $line): string => '- '.$line, $plan)),
                ],
                'items' => $this->freezeItems($cards),
            ];

            /** @var Report $report */
            $report = $existing instanceof Report
                ? $this->reports->update($existing, $payload)
                : $this->reports->create($payload);

            if ($submit) {
                $next = $this->nextWorkday($day);

                foreach ($extra as $line) {
                    $this->create(['title' => $line, 'status' => WorkItemStatus::Planned->value, 'work_on' => $next->format('Y-m-d'), 'work_time' => '09:00', 'personnel_id' => (int) $me->getKey()]);
                }

                $report = $this->reports->submit($report);
            }

            return $report;
        });
    }

    /**
     * Haftayi kapat: haftanin kartlari donar; ozet zorunlu; gelecek hafta
     * planina eklenen satirlar gelecek pazartesinin panosuna kart olur.
     * Amir inceler (haftalik calisma raporu taslagi).
     *
     * @param  array{summary?: string|null, achievements?: string|null, blockers?: string|null, plan?: list<string>, extra_plan?: list<string>}  $data
     */
    public function closeWeek(string $date, array $data, bool $submit): Report
    {
        $me = $this->actorPersonnel();
        $start = WorkItemQueries::weekStart(WorkItemQueries::day($date));
        $end = $start->copy()->addDays(6);
        $existing = $this->queries->weeklyReport((int) $me->getKey(), $start);

        if ($existing instanceof Report && ! $existing->status->isEditable()) {
            throw WeekAlreadyClosedException::make(['no' => (string) $existing->report_no]);
        }

        $cards = $this->queries->periodCards((int) $me->getKey(), $start, $end);

        return $this->transactions->run(function () use ($me, $start, $end, $existing, $cards, $data, $submit): Report {
            $extra = $this->planLines($data['extra_plan'] ?? []);
            $plan = [...$this->planLines($data['plan'] ?? []), ...$extra];

            $payload = [
                'template_code' => WeeklyWorkReportTemplate::CODE,
                'period_start' => $start->format('Y-m-d'),
                'period_end' => $end->format('Y-m-d'),
                'payload' => [
                    'summary' => filled($data['summary'] ?? null) ? trim((string) $data['summary']) : null,
                    'achievements' => filled($data['achievements'] ?? null) ? trim((string) $data['achievements']) : null,
                    'blockers' => filled($data['blockers'] ?? null) ? trim((string) $data['blockers']) : null,
                    'next_week_plan' => $plan === [] ? null : implode("\n", array_map(static fn (string $line): string => '- '.$line, $plan)),
                ],
                'items' => $this->freezeItems($cards),
            ];

            /** @var Report $report */
            $report = $existing instanceof Report
                ? $this->reports->update($existing, $payload)
                : $this->reports->create($payload);

            if ($submit) {
                if (! filled($payload['payload']['summary'])) {
                    throw WeekSummaryRequiredException::make();
                }

                $monday = $start->copy()->addWeek();

                foreach ($extra as $line) {
                    $this->create(['title' => $line, 'status' => WorkItemStatus::Planned->value, 'work_on' => $monday->format('Y-m-d'), 'work_time' => '09:00', 'personnel_id' => (int) $me->getKey()]);
                }

                $report = $this->reports->submit($report);
            }

            return $report;
        });
    }

    /**
     * Yonetim panosunu dondur: gorunen kartlar o gunun tarihli raporu olur
     * (koordinasyon listesi). Ayni gun ikinci dondurma acilmaz.
     *
     * @param  list<int>  $ids
     */
    public function freezeBoard(array $ids, ?string $summary): Report
    {
        $me = $this->actorPersonnel();
        $today = WorkItemQueries::today();
        $cards = $this->queries->visibleCards($me, $ids);

        return $this->transactions->run(function () use ($today, $cards, $summary): Report {
            /** @var Report $report */
            $report = $this->reports->create([
                'template_code' => CoordinationBoardReportTemplate::CODE,
                'period_start' => $today->format('Y-m-d'),
                'period_end' => $today->format('Y-m-d'),
                'payload' => ['summary' => filled($summary) ? trim((string) $summary) : $this->generatedSummary($cards)],
                'items' => $this->freezeItems($cards, withOwner: true),
            ]);

            return $this->reports->submit($report);
        });
    }

    /**
     * Toplu durum degisikligi (Isler listesi).
     *
     * @param  list<int>  $ids
     */
    public function changeStatusMany(array $ids, string $status, array $extra = []): int
    {
        $count = 0;

        foreach ($ids as $id) {
            $this->changeStatus((int) $id, $status, $extra);
            $count++;
        }

        return $count;
    }

    /**
     * Toplu projeye atama (Isler listesi).
     *
     * @param  list<int>  $ids
     */
    public function assignProject(array $ids, ?int $projectId): int
    {
        return $this->transactions->run(function () use ($ids, $projectId): int {
            $count = 0;

            foreach ($ids as $id) {
                /** @var WorkItem $item */
                $item = $this->lockForUpdate((int) $id);
                $before = $item->project?->name;
                $item->project_id = $projectId;

                if ($item->isDirty('project_id')) {
                    $this->saveWithoutVersion($item);
                    $item->load('project:id,name');
                    $this->recordActivity($item, 'updated', ['proje' => ['onceki' => $before, 'yeni' => $item->project?->name]]);
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * Toplu kritik isaretleme (Isler listesi).
     *
     * @param  list<int>  $ids
     */
    public function markCriticalMany(array $ids, bool $critical): int
    {
        $count = 0;

        foreach ($ids as $id) {
            $this->setCritical((int) $id, $critical);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return $record instanceof WorkItem ? $this->summary($record) : parent::createdChanges($record);
    }

    /**
     * Gelen veriyi kart kolonlarina cevirir (durum haric).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?WorkItem $item, Personnel $me): array
    {
        $attributes = [];
        $zone = DisplayTime::zone();

        if ($item === null || array_key_exists('title', $data)) {
            $attributes['title'] = mb_substr(Str::squish((string) ($data['title'] ?? $item?->title ?? '')), 0, 200);
        }

        // Sorumlu ve departman.
        $personnelId = filled($data['personnel_id'] ?? null) ? (int) $data['personnel_id'] : ($item?->personnel_id ?? (int) $me->getKey());

        if ($item === null || (int) $personnelId !== (int) $item->personnel_id) {
            $this->assertAssignee($me, (int) $personnelId);
            $attributes['personnel_id'] = (int) $personnelId;
        }

        if (array_key_exists('org_unit_id', $data) && filled($data['org_unit_id'])) {
            $attributes['org_unit_id'] = (int) $data['org_unit_id'];
        } elseif ($item === null || isset($attributes['personnel_id'])) {
            $unit = Personnel::query()->whereKey($personnelId)->value('org_unit_id');
            $attributes['org_unit_id'] = $unit !== null ? (int) $unit : null;
        }

        foreach (['project_id', 'parent_id', 'waiting_personnel_id', 'waiting_party_id'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = filled($data[$key]) ? (int) $data[$key] : null;
            }
        }

        if (array_key_exists('category_code', $data)) {
            $category = filled($data['category_code']) ? (string) $data['category_code'] : null;
            $attributes['category_code'] = $category !== null && $this->categories->isKnown($category) ? $category : null;
        }

        if (array_key_exists('is_critical', $data)) {
            $attributes['is_critical'] = (bool) $data['is_critical'];
        }

        if (array_key_exists('due_on', $data)) {
            $attributes['due_on'] = filled($data['due_on']) ? Carbon::parse((string) $data['due_on'])->format('Y-m-d') : null;
        }

        if (array_key_exists('work_hours', $data)) {
            $hours = $data['work_hours'];
            $attributes['work_hours'] = is_numeric($hours) ? round(min(999, max(0, (float) $hours)), 2) : null;
        }

        if (array_key_exists('note', $data)) {
            $note = trim((string) ($data['note'] ?? ''));
            $attributes['note'] = $note === '' ? null : mb_substr($note, 0, 2000);
        }

        foreach (['source', 'source_activity_id'] as $key) {
            if ($item === null && array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }

        // Is tarihi: gun + saat (kurum saati) ya da ISO an.
        if ($item === null || array_key_exists('work_on', $data) || array_key_exists('work_time', $data) || array_key_exists('work_at', $data)) {
            $attributes = [...$attributes, ...$this->workMoment($data, $item, $zone)];
        } else {
            $attributes['work_on'] = $item->work_on?->format('Y-m-d');
        }

        // Kimden bekleniyor.
        if (array_key_exists('waiting_kind', $data)) {
            $attributes = [...$attributes, ...$this->waitingAttributes($data, $item)];
        }

        // Talep eden (B37, D-118).
        if (array_key_exists('requester_kind', $data) && SchemaReadiness::hasBatch('B37')) {
            $attributes = [...$attributes, ...$this->requesterAttributes($data)];
        }

        // Bagli kayit.
        if (array_key_exists('link_kind', $data)) {
            $attributes = [...$attributes, ...$this->linkAttributes($data)];
        }

        // Ana is: kendisi, alt karti ya da alt kartin altindaki kart secilemez.
        $parentId = $attributes['parent_id'] ?? null;

        if ($parentId !== null) {
            $parent = WorkItem::query()->find($parentId, ['id', 'parent_id']);

            if (! $parent instanceof WorkItem
                || ($item !== null && (int) $parent->getKey() === (int) $item->getKey())
                || $parent->parent_id !== null
                || ($item !== null && $this->queries->hasChildren((int) $item->getKey()))) {
                throw InvalidParentException::make();
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{work_at: Carbon, work_on: string}
     */
    private function workMoment(array $data, ?WorkItem $item, string $zone): array
    {
        if (filled($data['work_at'] ?? null) && ! filled($data['work_on'] ?? null)) {
            $local = Carbon::parse((string) $data['work_at'])->timezone($zone);
        } else {
            $existing = $item?->work_at?->copy()->timezone($zone);
            $dayValue = filled($data['work_on'] ?? null) ? (string) $data['work_on'] : ($existing?->format('Y-m-d') ?? Carbon::now($zone)->format('Y-m-d'));
            $timeValue = filled($data['work_time'] ?? null) ? (string) $data['work_time'] : ($existing?->format('H:i') ?? Carbon::now($zone)->format('H:i'));

            if (preg_match('/^\d{2}:\d{2}/', $timeValue) !== 1) {
                $timeValue = Carbon::now($zone)->format('H:i');
            }

            $local = Carbon::createFromFormat('Y-m-d H:i', Carbon::parse($dayValue)->format('Y-m-d').' '.substr($timeValue, 0, 5), $zone);
        }

        return [
            'work_at' => $local->copy()->timezone('UTC'),
            'work_on' => $local->format('Y-m-d'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function waitingAttributes(array $data, ?WorkItem $item): array
    {
        $kind = WorkWaitingKind::tryFrom((string) ($data['waiting_kind'] ?? ''));

        if ($kind === null) {
            return ['waiting_kind' => null, 'waiting_personnel_id' => null, 'waiting_party_id' => null, 'waiting_text' => null];
        }

        $text = trim((string) ($data['waiting_text'] ?? ''));

        return match ($kind) {
            WorkWaitingKind::Personnel => [
                'waiting_kind' => $kind->value,
                'waiting_personnel_id' => filled($data['waiting_personnel_id'] ?? null) ? (int) $data['waiting_personnel_id'] : null,
                'waiting_party_id' => null,
                'waiting_text' => null,
            ],
            WorkWaitingKind::Party => [
                'waiting_kind' => $kind->value,
                'waiting_personnel_id' => null,
                'waiting_party_id' => filled($data['waiting_party_id'] ?? null) && Party::query()->whereKey((int) $data['waiting_party_id'])->exists()
                    ? (int) $data['waiting_party_id']
                    : null,
                'waiting_text' => $text === '' ? null : mb_substr($text, 0, 200),
            ],
            WorkWaitingKind::Text => [
                'waiting_kind' => $kind->value,
                'waiting_personnel_id' => null,
                'waiting_party_id' => null,
                'waiting_text' => $text === '' ? null : mb_substr($text, 0, 200),
            ],
        };
    }

    /**
     * Talep eden (B37, D-118): personel, taraf ya da serbest metin. Bos
     * birakilirsa kisinin kendi isidir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function requesterAttributes(array $data): array
    {
        $kind = WorkWaitingKind::tryFrom((string) ($data['requester_kind'] ?? ''));
        $empty = ['requester_kind' => null, 'requester_personnel_id' => null, 'requester_party_id' => null, 'requester_text' => null];

        if ($kind === null) {
            return $empty;
        }

        $text = trim((string) ($data['requester_text'] ?? ''));
        $text = $text === '' ? null : mb_substr($text, 0, 200);

        return match ($kind) {
            WorkWaitingKind::Personnel => filled($data['requester_personnel_id'] ?? null)
                ? [...$empty, 'requester_kind' => $kind->value, 'requester_personnel_id' => (int) $data['requester_personnel_id']]
                : $empty,
            WorkWaitingKind::Party => filled($data['requester_party_id'] ?? null) && Party::query()->whereKey((int) $data['requester_party_id'])->exists()
                ? [...$empty, 'requester_kind' => $kind->value, 'requester_party_id' => (int) $data['requester_party_id'], 'requester_text' => $text]
                : $empty,
            WorkWaitingKind::Text => $text === null
                ? $empty
                : [...$empty, 'requester_kind' => $kind->value, 'requester_text' => $text],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function linkAttributes(array $data): array
    {
        $kind = WorkItemLinkKind::tryFrom((string) ($data['link_kind'] ?? '')) ?? WorkItemLinkKind::None;
        $id = filled($data['link_id'] ?? null) ? (int) $data['link_id'] : null;
        $attributes = ['link_kind' => WorkItemLinkKind::None->value];

        foreach (WorkItemLinkKind::columns() as $column) {
            $attributes[$column] = null;
        }

        if ($kind === WorkItemLinkKind::None || $id === null) {
            return $attributes;
        }

        if (! $this->queries->linkExists($kind, $id)) {
            throw LinkedRecordNotFoundException::make();
        }

        $attributes['link_kind'] = $kind->value;
        $attributes[(string) $kind->column()] = $id;

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attributes
     */
    private function initialStatus(array $data, array $attributes): WorkItemStatus
    {
        $raw = $data['status'] ?? null;
        $status = $raw instanceof WorkItemStatus ? $raw : (WorkItemStatus::tryFrom((string) ($raw ?? '')) ?? WorkItemStatus::Planned);

        // Ileri tarih verilirse kart Planlandi sayilir ve o gun panoya duser.
        if ($attributes['work_on'] > WorkItemQueries::today()->format('Y-m-d') && $status->isOpen()) {
            return WorkItemStatus::Planned;
        }

        return $status;
    }

    /**
     * Durum gecisi: onceki durumun suresini kovasina ekler, gecis anlarini yazar.
     */
    private function transition(WorkItem $item, WorkItemStatus $target, Carbon $now): bool
    {
        $from = $item->status;

        if ($from === $target) {
            return false;
        }

        $bucket = $from?->durationBucket();

        if ($bucket !== null && $item->status_changed_at !== null) {
            $elapsed = max(0, (int) $item->status_changed_at->diffInSeconds($now, true));
            $column = $bucket.'_seconds';
            $item->{$column} = (int) $item->{$column} + $elapsed;
        }

        $item->status = $target;
        $item->status_changed_at = $now;

        if ($target === WorkItemStatus::InProgress && $item->started_at === null) {
            $item->started_at = $now;
        }

        // Kapanis: Tamamlandi ya da Iptal (eski "Engellendi"); kapali bir
        // durumdan acik duruma donen kartin kapanisi silinir.
        if (! $target->isOpen()) {
            $item->completed_at = $now;
            $item->done_on = WorkItemQueries::today()->format('Y-m-d');
        } elseif ($from !== null && ! $from->isOpen()) {
            $item->completed_at = null;
            $item->done_on = null;
        }

        $item->waiting_since = $target === WorkItemStatus::Waiting ? $now : null;

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertWaiting(WorkItemStatus $status, array $attributes): void
    {
        if ($status !== WorkItemStatus::Waiting) {
            return;
        }

        $kind = $attributes['waiting_kind'] ?? null;
        $kind = $kind instanceof WorkWaitingKind ? $kind : WorkWaitingKind::tryFrom((string) $kind);

        $ok = match ($kind) {
            WorkWaitingKind::Personnel => filled($attributes['waiting_personnel_id'] ?? null),
            WorkWaitingKind::Party => filled($attributes['waiting_party_id'] ?? null) || filled($attributes['waiting_text'] ?? null),
            WorkWaitingKind::Text => filled($attributes['waiting_text'] ?? null),
            default => false,
        };

        if (! $ok) {
            throw WaitingSourceRequiredException::make();
        }
    }

    /** Baskasina kart yazmak: amiri, departman yoneticisi ya da tum kartlari goren. */
    private function assertAssignee(Personnel $me, int $personnelId): void
    {
        if ($personnelId === (int) $me->getKey()) {
            return;
        }

        if (! Personnel::query()->whereKey($personnelId)->exists()) {
            throw RecordNotFoundException::make();
        }

        if ($this->queries->seesAll($me) || $this->reportQueries->managesPersonnel((int) $me->getKey(), $personnelId)) {
            return;
        }

        throw AssigneeNotAllowedException::make();
    }

    /** Kapatilmis gune yazilan kart: o gunun raporuna "sonradan eklendi" kalemi. */
    private function appendToClosedDay(WorkItem $item): void
    {
        if ($item->work_on === null) {
            return;
        }

        $report = $this->queries->dailyReport((int) $item->personnel_id, WorkItemQueries::day($item->work_on->format('Y-m-d')));

        if (! $report instanceof Report || $report->status->isEditable()) {
            return;
        }

        if ($report->items()->where('work_item_id', (int) $item->getKey())->exists()) {
            return;
        }

        $item->loadMissing($this->queries->relations());

        ReportItem::query()->create([
            'report_id' => (int) $report->getKey(),
            'sort_order' => (int) $report->items()->max('sort_order') + 1,
            ...$this->freezeRow($item),
            'is_late' => true,
        ]);

        $this->recordActivity($item, 'added_late', ['rapor_no' => $report->report_no]);
    }

    /**
     * Kartlari donmus rapor kalemine cevirir.
     *
     * @param  Collection<int, WorkItem>  $cards
     * @return list<array<string, mixed>>
     */
    private function freezeItems(Collection $cards, bool $withOwner = false): array
    {
        $order = array_flip(array_map(static fn (WorkItemStatus $status): string => $status->value, WorkItemStatus::cases()));

        return $cards
            ->sortBy(fn (WorkItem $item): string => sprintf('%02d-%05d-%010d', $order[$item->status?->value] ?? 9, (int) $item->sort_order, (int) $item->getKey()))
            ->values()
            ->map(fn (WorkItem $item): array => $this->freezeRow($item, $withOwner))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function freezeRow(WorkItem $item, bool $withOwner = false): array
    {
        $meta = [];

        if ($withOwner && $item->personnel !== null) {
            $meta[] = (string) $item->personnel->full_name;
        }

        if ($item->orgUnit !== null && $withOwner) {
            $meta[] = (string) $item->orgUnit->name;
        }

        if ($item->categoryLabel() !== null) {
            $meta[] = (string) $item->categoryLabel();
        }

        if ($item->is_critical) {
            $meta[] = (string) __('work_item.values.critical');
        }

        if ($item->status === WorkItemStatus::Waiting && $item->waitingLabel() !== null) {
            $meta[] = __('work_item.values.waiting_meta', ['who' => $item->waitingLabel(), 'days' => (int) $item->waitingDays()]);
        }

        $link = $this->presenter->link($item);

        if ($link !== null) {
            $meta[] = trim(($link['no'] ?? '').' '.$link['label']);
        }

        if ($item->source === WorkItemSource::Automatic) {
            $meta[] = (string) __('work_item.values.automatic');
        }

        if ($item->status === WorkItemStatus::Blocked && filled($item->note)) {
            $meta[] = Str::limit(Str::squish((string) $item->note), 200, '…');
        }

        return [
            'title' => (string) $item->title,
            'description' => $meta === [] ? null : mb_substr(implode(' · ', $meta), 0, 1000),
            'status' => ($item->status ?? WorkItemStatus::Planned)->reportItemStatus()->value,
            'project_id' => $item->project_id !== null ? (int) $item->project_id : null,
            'work_hours' => $item->work_hours !== null ? (float) $item->work_hours : null,
            'due_on' => $item->due_on?->format('Y-m-d'),
            'work_item_id' => (int) $item->getKey(),
            'is_late' => false,
        ];
    }

    /**
     * @param  Collection<int, WorkItem>  $cards
     */
    private function generatedSummary(Collection $cards): string
    {
        $done = $cards->filter(fn (WorkItem $item): bool => $item->status === WorkItemStatus::Done)->pluck('title')->all();
        // Iptal edilen is (eski "Engellendi") suren is sayilmaz.
        $open = $cards->filter(fn (WorkItem $item): bool => in_array($item->status, [WorkItemStatus::InProgress, WorkItemStatus::Waiting], true))->pluck('title')->all();
        $parts = [];

        if ($done !== []) {
            $parts[] = __('work_item.values.summary_done', ['items' => implode(', ', $done)]);
        }

        if ($open !== []) {
            $parts[] = __('work_item.values.summary_open', ['items' => implode(', ', $open)]);
        }

        return $parts === [] ? (string) __('work_item.values.summary_empty') : Str::limit(implode(' ', $parts), 1500, '…');
    }

    /**
     * @param  mixed  $lines
     * @return list<string>
     */
    private function planLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($line): string => mb_substr(Str::squish((string) $line), 0, 200),
            $lines,
        ), static fn (string $line): bool => $line !== ''));
    }

    private function nextWorkday(Carbon $day): Carbon
    {
        $next = $day->copy()->addDay();

        while ($next->isWeekend()) {
            $next->addDay();
        }

        return $next;
    }

    /**
     * Hareket ozeti (Turkce alan adlari, etiketli degerler).
     *
     * @return array<string, mixed>
     */
    /**
     * Is artik bu kisiden bekleniyorsa ona zil bildirimi gonderir (23 Eylul
     * 2026 kullanici bildirimi: beklenen kisinin haberi olmuyordu). Kisi
     * degismediyse ya da kendisi isaretlediyse bildirim gitmez.
     */
    private function notifyAwaited(WorkItem $item, ?int $previousId): void
    {
        $targetId = $item->status === WorkItemStatus::Waiting && $item->waiting_personnel_id !== null
            ? (int) $item->waiting_personnel_id
            : null;

        if ($targetId === null || $targetId === $previousId || $targetId === $this->actor->personnelId()) {
            return;
        }

        $target = Personnel::query()->find($targetId);

        if (! $target instanceof Personnel || ! $target->isReachable()) {
            return;
        }

        $item->loadMissing('personnel:id,full_name');
        $actions = [];

        try {
            $actions[] = Action::make('open')
                ->label(__('work_item.notifications.awaited.open'))
                ->button()
                ->url(WorkItemResource::getUrl('view', ['record' => $item]))
                ->markAsRead();
        } catch (Throwable) {
            // Rota yoksa dugmesiz bildirim.
        }

        $this->notifier->send(
            [$target],
            __('work_item.notifications.awaited.title', ['name' => (string) ($item->personnel?->full_name ?? '')]),
            __('work_item.notifications.awaited.body', ['title' => (string) $item->title]),
            Heroicon::OutlinedClock,
            'warning',
            $actions,
        );
    }

    private function summary(WorkItem $item): array
    {
        $item->loadMissing(['personnel:id,full_name', 'project:id,name', 'parent:id,title', 'orgUnit:id,name']);
        $link = $item->link_kind !== null && $item->link_kind !== WorkItemLinkKind::None ? $this->presenter->link($item) : null;

        return array_filter([
            'baslik' => $item->title,
            'durum' => $item->status?->getLabel(),
            'sorumlu' => $item->personnel?->full_name,
            'departman' => $item->orgUnit?->name,
            'proje' => $item->project?->name,
            'ana_is' => $item->parent?->title,
            'kategori' => $item->categoryLabel(),
            'tarih' => DisplayTime::format($item->work_at),
            'termin' => $item->due_on?->format('d.m.Y'),
            'kritik' => $item->is_critical ? true : null,
            'kimden_bekleniyor' => $item->waiting_kind !== null ? $item->waitingLabel() : null,
            'talep_eden' => $item->requester_kind !== null ? $item->requesterLabel() : null,
            'bagli_kayit' => $link !== null ? trim(($link['no'] ?? '').' '.$link['label']) : null,
            'kaynak' => $item->source?->getLabel(),
            'saat' => $item->work_hours !== null ? (float) $item->work_hours : null,
            'not' => $item->note,
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{onceki: mixed, yeni: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old !== $new) {
                $changes[$key] = ['onceki' => $old, 'yeni' => $new];
            }
        }

        return $changes;
    }

    private function actorPersonnel(): Personnel
    {
        $id = $this->actor->personnelId();
        $personnel = $id !== null ? Personnel::query()->with('orgUnit')->find($id) : null;

        if (! $personnel instanceof Personnel) {
            throw ActorRequiredException::make();
        }

        return $personnel;
    }
}
