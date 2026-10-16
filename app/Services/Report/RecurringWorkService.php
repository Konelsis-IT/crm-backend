<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\Report\WorkItemStatus;
use App\Services\Audit\ActorContext;
use App\Query\Report\WorkItemQueries;
use App\Query\Report\ReportQueries;
use App\Exceptions\Report\AssigneeNotAllowedException;
use App\Enums\Platform\Feature;
use App\Enums\Report\RecurrenceFrequency;
use App\Enums\Report\RecurringWorkKind;
use App\Enums\Report\WorkItemPriority;
use App\Exceptions\RecordNotFoundException;
use App\Models\Personnel\Personnel;
use App\Models\Report\RecurringWork;
use App\Models\Report\WorkItem;
use App\Query\Report\RecurringWorkQueries;
use App\Reports\Work\WorkCategoryCatalog;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Gorev ve sorumluluklar (D-219; kullanici 16 Ekim 2026: "Rapor Kontrol sisteme
 * sabitli tekrarlı iş olarak işleyecek, sisteme her personel için tekrarlı iş
 * sistemi eklemeliyiz ... Uygun periyotlarda sürekli yapıp yapmadığı kontrolü
 * sağlanabilecek. İşlerin tamamını zaten iş olarak işleyecek.").
 *
 * Tanim: kim, ne, hangi siklikla (gunluk / haftalik / aylik), hangi gun. Her
 * donem sorumlunun panosuna bir is acilir (WorkItemService::openRecurring);
 * ayni donemin ikinci isi acilmaz (`recurring_work_id` + `period_key` tekil).
 * Tanim silinmez, arsivlenir ya da pasife alinir (D-156).
 */
final class RecurringWorkService extends AbstractService
{
    protected string $model = RecurringWork::class;

    protected string $orderBy = 'title';

    private bool $importing = false;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly RecurringWorkQueries $queries,
        private readonly WorkCategoryCatalog $categories,
        private readonly WorkItemService $items,
        private readonly ActorContext $actor,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Tanim; acilis gunu gectiyse bu donemin isi de hemen acilir.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        /** @var RecurringWork $work */
        $work = parent::create($data);

        if ($work->is_active && $this->enabled()) {
            $this->openPeriod($work, RecurrenceFrequency::today());
        }

        return $work;
    }

    /**
     * Aktarilan tanim (D-219, Rapor Tanimlari): tanimi yapan kisinin amirlik
     * kurali aranmaz; gecmis donemlerin isleri aktarimda ayrica acilir.
     *
     * @param  array<string, mixed>  $data
     */
    public function import(array $data): RecurringWork
    {
        $this->importing = true;

        try {
            /** @var RecurringWork */
            return parent::create($data);
        } finally {
            $this->importing = false;
        }
    }

    /** Arsivle / arsivden cikar (D-156). Arsivdeki tanimin donem isi acilmaz. */
    public function setArchived(Model|int|string $record, bool $archived): RecurringWork
    {
        return $this->transactions->run(function () use ($record, $archived): RecurringWork {
            /** @var RecurringWork $work */
            $work = $this->lockForUpdate($record);

            if (($work->archived_at !== null) === $archived) {
                return $work;
            }

            $work->archived_at = $archived ? Carbon::now('UTC') : null;
            $this->saveWithoutVersion($work);
            $this->recordActivity($work, $archived ? 'archived' : 'unarchived', ['baslik' => $work->title]);

            return $work;
        });
    }

    /**
     * Gunun donem islerini acar (zamanlayici her sabah; tekrar calismak
     * zararsizdir). Donen: acilan is sayisi.
     */
    public function openDue(?CarbonImmutable $day = null): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $day ??= RecurrenceFrequency::today();
        $opened = 0;

        foreach ($this->queries->activeOn($day) as $work) {
            if ($this->openPeriod($work, $day) instanceof WorkItem) {
                $opened++;
            }
        }

        return $opened;
    }

    /**
     * Tanimin bu donemki isi: acilis gunu geldiyse ve donemin isi yoksa acar.
     * Pasif, arsivdeki ya da suresi dolmus tanimda null.
     */
    public function openPeriod(RecurringWork $work, CarbonImmutable $day, WorkItemStatus $status = WorkItemStatus::Planned): ?WorkItem
    {
        $frequency = $work->frequency;

        if (! $frequency instanceof RecurrenceFrequency || ! $work->is_active || $work->archived_at !== null) {
            return null;
        }

        $opening = $frequency->openingDay($day, $work->weekday, $work->month_day);
        $starts = $work->starts_on !== null ? CarbonImmutable::parse($work->starts_on->format('Y-m-d'), $day->getTimezone()) : $day;

        if ($opening === null || $opening->greaterThan($day) || ($work->ends_on !== null && $work->ends_on->format('Y-m-d') < $day->format('Y-m-d'))) {
            return null;
        }

        // Tanim donemin ortasinda baslarsa ilk is baslangic gununde acilir.
        if ($starts->greaterThan($day)) {
            return null;
        }

        $key = $frequency->periodKey($day);

        if ($this->queries->occurrenceExists((int) $work->getKey(), $key)) {
            return null;
        }

        $workOn = $opening->lessThan($starts) ? $starts : $opening;

        return $this->items->openRecurring($work, $key, $workOn, $frequency->dueDay($day, $work->weekday, $work->month_day), $frequency->periodLabel($day), $status);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version']);
        $out = [];

        foreach (['title', 'description'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = trim((string) ($data[$key] ?? ''));
                $out[$key] = $key === 'title' ? mb_substr(Str::squish($value), 0, 200) : ($value === '' ? null : mb_substr($value, 0, 5000));
            }
        }

        if ($record === null || array_key_exists('personnel_id', $data)) {
            $personnelId = (int) ($data['personnel_id'] ?? 0);

            if ($personnelId <= 0 || ! Personnel::query()->whereKey($personnelId)->exists()) {
                throw RecordNotFoundException::make();
            }

            $out['personnel_id'] = $personnelId;
            $this->assertAssignable($personnelId, $record);
        }

        if ($record === null || array_key_exists('org_unit_id', $data)) {
            $unit = filled($data['org_unit_id'] ?? null)
                ? (int) $data['org_unit_id']
                : Personnel::query()->whereKey((int) ($out['personnel_id'] ?? $record?->personnel_id))->value('org_unit_id');
            $out['org_unit_id'] = $unit !== null ? (int) $unit : null;
        }

        if (array_key_exists('project_id', $data)) {
            $out['project_id'] = filled($data['project_id']) ? (int) $data['project_id'] : null;
        }

        if (array_key_exists('category_code', $data)) {
            $code = filled($data['category_code']) ? (string) self::raw($data['category_code']) : null;
            $out['category_code'] = $code !== null && $this->categories->isKnown($code) ? $code : null;
        }

        if ($record === null || array_key_exists('kind', $data)) {
            $out['kind'] = (RecurringWorkKind::tryFrom(self::raw($data['kind'] ?? null)) ?? RecurringWorkKind::Task)->value;
        }

        if ($record === null || array_key_exists('frequency', $data)) {
            $out['frequency'] = (RecurrenceFrequency::tryFrom(self::raw($data['frequency'] ?? null)) ?? RecurrenceFrequency::Weekly)->value;
        }

        if ($record === null || array_key_exists('priority', $data)) {
            $out['priority'] = (WorkItemPriority::tryFrom(self::raw($data['priority'] ?? null)) ?? WorkItemPriority::Normal)->value;
        }

        $frequency = RecurrenceFrequency::tryFrom((string) ($out['frequency'] ?? self::raw($record?->frequency)));

        if ($record === null || array_key_exists('weekday', $data) || array_key_exists('frequency', $data)) {
            $weekday = filled($data['weekday'] ?? null) ? (int) $data['weekday'] : null;
            $out['weekday'] = $frequency === RecurrenceFrequency::Weekly && $weekday !== null ? max(1, min(7, $weekday)) : null;
        }

        if ($record === null || array_key_exists('month_day', $data) || array_key_exists('frequency', $data)) {
            $monthDay = filled($data['month_day'] ?? null) ? (int) $data['month_day'] : null;
            $out['month_day'] = $frequency === RecurrenceFrequency::Monthly && $monthDay !== null ? max(1, min(31, $monthDay)) : null;
        }

        if ($record === null || array_key_exists('is_active', $data)) {
            $out['is_active'] = (bool) ($data['is_active'] ?? true);
        }

        if ($record === null || array_key_exists('starts_on', $data)) {
            $out['starts_on'] = filled($data['starts_on'] ?? null) ? Carbon::parse((string) $data['starts_on'])->format('Y-m-d') : RecurrenceFrequency::today()->format('Y-m-d');
        }

        if (array_key_exists('ends_on', $data)) {
            $out['ends_on'] = filled($data['ends_on']) ? Carbon::parse((string) $data['ends_on'])->format('Y-m-d') : null;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        if (! $record instanceof RecurringWork) {
            return parent::createdChanges($record);
        }

        $record->loadMissing('personnel:id,full_name');

        return array_filter([
            'baslik' => $record->title,
            'sorumlu' => $record->personnel?->full_name,
            'siklik' => $record->frequency?->getLabel(),
            'tur' => $record->kind?->getLabel(),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * Gorev tanimlamak (D-219): amiri ya da departman yoneticisi, ust yonetim ve
     * tum kartlari goren. Sistemin kendi islemi (zamanlayici) aktorsuzdur.
     */
    private function assertAssignable(int $personnelId, ?Model $record): void
    {
        $actorId = $this->actor->personnelId();

        if ($actorId === null || $this->importing || ($record instanceof RecurringWork && (int) $record->personnel_id === $personnelId)) {
            return;
        }

        $actor = Personnel::query()->find($actorId);

        if ($actor instanceof Personnel
            && (app(WorkItemQueries::class)->seesAll($actor) || app(ReportQueries::class)->managesPersonnel($actorId, $personnelId))) {
            return;
        }

        throw AssigneeNotAllowedException::make();
    }

    private function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B57') && FeatureFlags::enabled(Feature::WorkRecurring);
    }

    private static function raw(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (is_scalar($value) ? (string) $value : '');
    }
}
