<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\Report\ReportAuthorRule;
use App\Enums\Report\ReportItemStatus;
use App\Enums\Report\ReportPeriodMode;
use App\Enums\Report\ReportReviewMode;
use App\Enums\Report\ReportStatus;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Report\AuthorNotAllowedException;
use App\Exceptions\Report\ControlSectionNotFoundException;
use App\Exceptions\Report\DuplicatePeriodReportException;
use App\Exceptions\Report\InvalidPeriodException;
use App\Exceptions\Report\PeriodRequiredException;
use App\Exceptions\Report\ReportLockedException;
use App\Exceptions\Report\ReviewCommentRequiredException;
use App\Exceptions\Report\SubjectRequiredException;
use App\Exceptions\Report\TemplateNotFoundException;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Personnel\Personnel;
use App\Models\Report\Report;
use App\Models\Report\ReportItem;
use App\Query\Report\ReportQueries;
use App\Reports\ReportField;
use App\Reports\ReportTemplate;
use App\Reports\ReportTemplateRegistry;
use App\Reports\Templates\DailyControlReportTemplate;
use App\Reports\Work\ControlSectionCatalog;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Notification\PanelNotifier;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Rapor yasam dongusu (D-86): taslak olustur/duzenle -> gonder -> incele
 * (onayla / revizyon iste / reddet); gonderim geri cekilebilir, taslak
 * silinebilir. Taslak (sablon) kodda tanimlidir; servis konu, donem ve yazar
 * kurallarini taslaktan okur, KPI projeksiyonunu gonderimde yazar.
 */
final class ReportService extends AbstractService
{
    protected string $orderBy = 'created_at';

    protected string $orderDirection = 'desc';

    /** @var list<string> */
    protected array $with = ['author', 'reviewer', 'authorOrgUnit'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly ReportTemplateRegistry $templates,
        private readonly ReportQueries $queries,
        private readonly PanelNotifier $notifier,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $me = $this->actorPersonnel();
        $template = $this->templates->get((string) ($data['template_code'] ?? ''));
        $data = $this->normalize($template, $data);
        $this->assertAuthor($template, $data, $me);
        $this->assertPeriodUnique($template, $data, (int) $me->getKey(), null);
        $items = $this->extractItems($template, $data);

        return $this->transactions->run(function () use ($data, $me, $template, $items): Report {
            /** @var Report $report */
            $report = parent::create([
                ...$data,
                'report_no' => 'RPR-TMP-'.bin2hex(random_bytes(6)),
                'template_code' => $template->code(),
                'kind' => $template->kind()->value,
                'subject_kind' => $template->subjectKind()->value,
                'is_confidential' => $template->isConfidential(),
                'status' => ReportStatus::Draft->value,
                'author_personnel_id' => (int) $me->getKey(),
                'author_org_unit_id' => $me->org_unit_id !== null ? (int) $me->org_unit_id : null,
                'title' => $this->resolveTitle($template, $data),
                'summary' => $template->summary($data['payload'] ?? []),
            ]);

            $report->forceFill(['report_no' => sprintf('RPR-%06d', (int) $report->getKey())])->save();

            if ($items !== null) {
                $this->syncItems($report, $items);
            }

            return $report->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        /** @var Report $report */
        $report = $this->show($record);

        if (! $report->status->isEditable()) {
            throw ReportLockedException::make(['status' => $report->status->getLabel()]);
        }

        $template = $report->template() ?? throw TemplateNotFoundException::make(['code' => (string) $report->template_code]);
        $data = $this->normalize($template, $data);
        $this->assertPeriodUnique($template, $data, (int) $report->author_personnel_id, (int) $report->getKey());
        $items = $this->extractItems($template, $data);

        return $this->transactions->run(function () use ($report, $data, $template, $items): Report {
            /** @var Report $updated */
            $updated = parent::update($report, [
                ...$data,
                'title' => $this->resolveTitle($template, $data),
                'summary' => $template->summary($data['payload'] ?? []),
            ]);

            if ($items !== null) {
                $this->syncItems($updated, $items);
            }

            return $updated->refresh();
        });
    }

    /** Yazar raporu gonderir; inceleyen taslagin kuralina gore atanir, KPI'lar yazilir. */
    public function submit(Model|int|string $record): Report
    {
        return $this->transactions->run(function () use ($record): Report {
            /** @var Report $report */
            $report = $this->lockForUpdate($record);
            $from = $report->status;

            if (! $from->canTransitionTo(ReportStatus::Submitted)) {
                throw InvalidTransitionException::make();
            }

            $template = $report->template() ?? throw TemplateNotFoundException::make(['code' => (string) $report->template_code]);
            $report->load(['author', 'items']);
            $reviewerId = $this->resolveReviewer($template, $report);

            $report->fill([
                'status' => ReportStatus::Submitted->value,
                'submitted_at' => Carbon::now('UTC'),
                'reviewer_personnel_id' => $reviewerId,
                'reviewed_at' => null,
                'review_comment' => null,
                'revision_count' => $from === ReportStatus::RevisionRequired ? (int) $report->revision_count + 1 : (int) $report->revision_count,
            ])->save();

            $this->writeMetrics($report, $template);
            $report->load($this->with);

            $this->recordActivity($report, 'submitted', [
                'rapor_no' => $report->report_no,
                'durum' => ['onceki' => $from->getLabel(), 'yeni' => ReportStatus::Submitted->getLabel()],
                'inceleyen' => $report->reviewer?->full_name,
            ]);

            // Rapor butun amirlere ve ust yonetime ayni anda gider (24 Eylul 2026).
            $recipients = Personnel::query()
                ->whereKey($this->queries->reportRecipientIds((int) $report->author_personnel_id))
                ->get()
                ->filter(fn (Personnel $person): bool => $person->isReachable());

            if ($report->reviewer instanceof Personnel && ! $recipients->contains(fn (Personnel $person): bool => $person->is($report->reviewer))) {
                $recipients->push($report->reviewer);
            }

            $this->notify($report, $recipients, 'submitted');

            return $report;
        });
    }

    /** Inceleme baslamadan gonderimi geri ceker (-> taslak). */
    public function withdraw(Model|int|string $record): Report
    {
        return $this->transactions->run(function () use ($record): Report {
            /** @var Report $report */
            $report = $this->lockForUpdate($record);

            if ($report->status !== ReportStatus::Submitted || $report->reviewed_at !== null) {
                throw InvalidTransitionException::make();
            }

            $report->fill([
                'status' => ReportStatus::Draft->value,
                'reviewer_personnel_id' => null,
                'submitted_at' => null,
            ])->save();

            $this->recordActivity($report, 'withdrawn', [
                'rapor_no' => $report->report_no,
                'durum' => ['onceki' => ReportStatus::Submitted->getLabel(), 'yeni' => ReportStatus::Draft->getLabel()],
            ]);

            return $report;
        });
    }

    public function approve(Model|int|string $record, ?string $comment = null): Report
    {
        return $this->decide($record, ReportStatus::Approved, 'approved', $comment, commentRequired: false);
    }

    public function requestRevision(Model|int|string $record, ?string $comment): Report
    {
        return $this->decide($record, ReportStatus::RevisionRequired, 'revision_required', $comment, commentRequired: true);
    }

    public function reject(Model|int|string $record, ?string $comment): Report
    {
        return $this->decide($record, ReportStatus::Rejected, 'rejected', $comment, commentRequired: true);
    }

    /**
     * Kontrol matrisi (B36, D-115; gunluk doldurma D-116): bir bolumun bir
     * gununu kaydeder. Kisi basina (bolum + gun) tek kontrol raporu vardir;
     * yoksa gonderilmis olarak acilir, varsa isaretleri ve aciklamasi
     * guncellenir. Isaretsiz ve aciklamasiz satir rapor acmaz. Rapor
     * formundan duzenlenmez; matris sahibidir. Haftalik gorunum bu gunluk
     * raporlarin toplamidir.
     *
     * @param  list<array{personnel_id: int, marks: array<string, string>, note: string|null}>  $rows
     * @return array{created: int, updated: int}
     */
    public function saveControlSheet(string $section, string $day, array $rows): array
    {
        $me = $this->actorPersonnel();
        $template = $this->templates->get(DailyControlReportTemplate::CODE);
        $catalog = app(ControlSectionCatalog::class);

        if (! $catalog->has($section)) {
            throw ControlSectionNotFoundException::make();
        }

        $start = Carbon::parse($day)->startOfDay();
        $end = $start->copy();
        $criteria = $catalog->manualCriteria($section);
        $sectionLabel = $catalog->sectionLabel($section);

        return $this->transactions->run(function () use ($rows, $me, $template, $catalog, $section, $sectionLabel, $start, $end, $criteria): array {
            $created = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $subjectId = (int) ($row['personnel_id'] ?? 0);
                $subject = $subjectId > 0 ? Personnel::query()->find($subjectId) : null;

                if (! $subject instanceof Personnel) {
                    continue;
                }

                $marks = [];

                foreach ($criteria as $criterion) {
                    $mark = $row['marks'][$criterion] ?? null;

                    if (in_array($mark, ['ok', 'bad'], true)) {
                        $marks[$criterion] = $mark;
                    }
                }

                $note = trim((string) ($row['note'] ?? ''));
                $note = $note === '' ? null : mb_substr($note, 0, 1000);
                $existing = $this->queries->controlReport($section, $subjectId, $start->format('Y-m-d'));

                if ($marks === [] && $note === null && $existing === null) {
                    continue;
                }

                $results = [];

                foreach ($marks as $criterion => $mark) {
                    $results[$catalog->criterionLabel($criterion)] = (string) __('work_item.control.marks.'.$mark);
                }

                $payload = [
                    'section' => $section,
                    'section_label' => $sectionLabel,
                    'marks' => $marks,
                    'results' => $results,
                    'note' => $note,
                ];

                if ($existing instanceof Report) {
                    /** @var Report $report */
                    $report = $this->lockForUpdate($existing);
                    $before = $report->payload ?? [];
                    $report->fill(['payload' => $payload, 'summary' => $note])->save();
                    $this->writeMetrics($report, $template);

                    if (($before['marks'] ?? []) !== $marks || ($before['note'] ?? null) !== $note) {
                        $this->recordActivity($report, 'control_updated', array_filter([
                            'rapor_no' => $report->report_no,
                            'bolum' => $sectionLabel,
                            'uygun' => count(array_filter($marks, static fn (string $mark): bool => $mark === 'ok')).' / '.count($marks),
                            'aciklama' => $note,
                        ], static fn ($value): bool => $value !== null));
                    }

                    $updated++;

                    continue;
                }

                /** @var Report $report */
                $report = parent::create([
                    'report_no' => 'RPR-TMP-'.bin2hex(random_bytes(6)),
                    'template_code' => $template->code(),
                    'kind' => $template->kind()->value,
                    'subject_kind' => $template->subjectKind()->value,
                    'subject_personnel_id' => $subjectId,
                    'is_confidential' => true,
                    'status' => ReportStatus::Submitted->value,
                    'submitted_at' => Carbon::now('UTC'),
                    'author_personnel_id' => (int) $me->getKey(),
                    'author_org_unit_id' => $me->org_unit_id !== null ? (int) $me->org_unit_id : null,
                    'period_start' => $start->format('Y-m-d'),
                    'period_end' => $end->format('Y-m-d'),
                    'title' => mb_substr(implode(' · ', [$template->name(), $sectionLabel, (string) $subject->full_name, $start->format('d.m.Y')]), 0, 200),
                    'summary' => $note,
                    'payload' => $payload,
                ]);

                $report->forceFill(['report_no' => sprintf('RPR-%06d', (int) $report->getKey())])->save();
                $this->writeMetrics($report, $template);
                $created++;
            }

            return ['created' => $created, 'updated' => $updated];
        });
    }

    /**
     * Raporu baska bir yoneticiye iletir (23 Eylul 2026 kullanici istegi):
     * yeni inceleyen atanir, rapor yeniden "gonderildi" durumuna gecer ve
     * hedefe bildirim gider. Onceki karar ve yorum gecmiste kalir.
     */
    public function forward(Model|int|string $record, int $reviewerId, ?string $comment = null): Report
    {
        return $this->transactions->run(function () use ($record, $reviewerId, $comment): Report {
            /** @var Report $report */
            $report = $this->lockForUpdate($record);
            $reviewer = Personnel::query()->find($reviewerId);

            if (! $reviewer instanceof Personnel || (int) $reviewer->getKey() === (int) $report->author_personnel_id) {
                throw AuthorNotAllowedException::make();
            }

            $previous = $report->reviewer?->full_name;

            $report->fill([
                'status' => ReportStatus::Submitted->value,
                'reviewer_personnel_id' => (int) $reviewer->getKey(),
                'reviewed_at' => null,
                'review_comment' => filled($comment) ? trim((string) $comment) : null,
            ])->save();

            $report->load($this->with);

            $this->recordActivity($report, 'forwarded', array_filter([
                'rapor_no' => $report->report_no,
                'onceki_inceleyen' => $previous,
                'yeni_inceleyen' => $reviewer->full_name,
                'aciklama' => filled($comment) ? trim((string) $comment) : null,
            ], static fn ($value): bool => $value !== null));

            $this->notify($report, collect([$reviewer]), 'forwarded');

            return $report;
        });
    }

    /** Yalniz taslak silinir; kalemler ve metrikler birlikte gider. */
    public function delete(Model|int|string $record): bool
    {
        return $this->transactions->run(function () use ($record): bool {
            /** @var Report $report */
            $report = $this->lockForUpdate($record);

            if ($report->status !== ReportStatus::Draft) {
                throw ReportLockedException::make(['status' => $report->status->getLabel()]);
            }

            $report->items()->delete();
            $report->metrics()->delete();

            return parent::delete($report);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version'], $data['items'], $data['template_help'], $data['author_name']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $no = (string) $record->getAttribute('report_no');

        if (str_starts_with($no, 'RPR-TMP-')) {
            $no = sprintf('RPR-%06d', (int) $record->getKey());
        }

        return [
            'rapor_no' => $no,
            'baslik' => $record->getAttribute('title'),
            'taslak' => $record instanceof Report ? $record->templateName() : $record->getAttribute('template_code'),
        ];
    }

    private function decide(Model|int|string $record, ReportStatus $target, string $operation, ?string $comment, bool $commentRequired): Report
    {
        if ($commentRequired && ! filled($comment)) {
            throw ReviewCommentRequiredException::make();
        }

        return $this->transactions->run(function () use ($record, $target, $operation, $comment): Report {
            /** @var Report $report */
            $report = $this->lockForUpdate($record);
            $from = $report->status;

            if (! $from->canTransitionTo($target) || $target === ReportStatus::Draft) {
                throw InvalidTransitionException::make();
            }

            $report->fill([
                'status' => $target->value,
                'reviewed_at' => Carbon::now('UTC'),
                'reviewer_personnel_id' => $report->reviewer_personnel_id ?? $this->actor->personnelId(),
                'review_comment' => filled($comment) ? trim((string) $comment) : null,
            ])->save();

            $report->load($this->with);

            $this->recordActivity($report, $operation, array_filter([
                'rapor_no' => $report->report_no,
                'durum' => ['onceki' => $from->getLabel(), 'yeni' => $target->getLabel()],
                'aciklama' => $report->review_comment,
            ], fn ($value): bool => $value !== null));

            if ($report->author instanceof Personnel) {
                $this->notify($report, collect([$report->author]), $operation);
            }

            return $report;
        });
    }

    /**
     * Konu, donem ve cevaplari taslaga gore duzenler; kayit disi anahtarlari atar.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(ReportTemplate $template, array $data): array
    {
        foreach (['template_code', 'kind', 'status', 'report_no', 'is_confidential', 'subject_kind', 'author_personnel_id', 'author_org_unit_id', 'reviewer_personnel_id', 'submitted_at', 'reviewed_at', 'review_comment', 'revision_count'] as $key) {
            unset($data[$key]);
        }

        // Konu: taslagin turu disindaki kolonlar bosaltilir; gerekli kolon dolu olmali.
        $subjectColumn = $template->subjectKind()->column();

        foreach (\App\Enums\Report\ReportSubjectKind::columns() as $column) {
            $value = $column === $subjectColumn ? ($data[$column] ?? null) : null;
            $data[$column] = filled($value) ? (int) $value : null;
        }

        if ($subjectColumn !== null && $data[$subjectColumn] === null) {
            throw SubjectRequiredException::make(['kind' => $template->subjectKind()->getLabel()]);
        }

        // Donem.
        [$data['period_start'], $data['period_end']] = $this->normalizePeriod($template, $data['period_start'] ?? null, $data['period_end'] ?? null);

        // Cevaplar: yalniz taslagin alanlari.
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $clean = [];

        foreach ($template->fieldList() as $field) {
            if (! array_key_exists($field->name, $payload)) {
                continue;
            }

            $value = $payload[$field->name];

            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }

            if ($field->type === ReportField::KEY_VALUE && is_array($value)) {
                $value = array_filter($value, fn ($item, $key): bool => trim((string) $key) !== '', ARRAY_FILTER_USE_BOTH);
            }

            $clean[$field->name] = $value;
        }

        $data['payload'] = $clean;

        if (array_key_exists('title', $data)) {
            $data['title'] = trim((string) $data['title']);
        }

        return $data;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function normalizePeriod(ReportTemplate $template, mixed $start, mixed $end): array
    {
        $mode = $template->periodMode();

        if ($mode === ReportPeriodMode::None) {
            return [null, null];
        }

        $startAt = filled($start) ? Carbon::parse((string) $start)->startOfDay() : null;
        $endAt = filled($end) ? Carbon::parse((string) $end)->startOfDay() : null;

        if ($mode->isCalendarUnit()) {
            if ($startAt === null) {
                if ($template->periodRequired()) {
                    throw PeriodRequiredException::make();
                }

                return [null, null];
            }

            [$startAt, $endAt] = match ($mode) {
                ReportPeriodMode::Day => [$startAt, $startAt->copy()],
                ReportPeriodMode::Week => [$startAt->copy()->startOfWeek(Carbon::MONDAY), $startAt->copy()->endOfWeek(Carbon::SUNDAY)],
                default => [$startAt->copy()->startOfMonth(), $startAt->copy()->endOfMonth()],
            };

            return [$startAt->format('Y-m-d'), $endAt->format('Y-m-d')];
        }

        if ($startAt === null && $endAt === null) {
            if ($template->periodRequired()) {
                throw PeriodRequiredException::make();
            }

            return [null, null];
        }

        if ($startAt === null || $endAt === null) {
            throw PeriodRequiredException::make();
        }

        if ($endAt->lessThan($startAt)) {
            throw InvalidPeriodException::make();
        }

        return [$startAt->format('Y-m-d'), $endAt->format('Y-m-d')];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertAuthor(ReportTemplate $template, array $data, Personnel $me): void
    {
        if ($this->queries->hasFullAccess($me)) {
            return;
        }

        $allowed = match ($template->authorRule()) {
            ReportAuthorRule::Anyone => true,
            ReportAuthorRule::SubjectManager => $this->queries->managesPersonnel((int) $me->getKey(), (int) ($data['subject_personnel_id'] ?? 0)),
            ReportAuthorRule::Hr => $this->queries->permits($me, 'authorHrEvaluation'),
            ReportAuthorRule::UnitManager => $this->queries->managedUnitIds((int) $me->getKey()) !== [],
        };

        if (! $allowed) {
            throw AuthorNotAllowedException::make(['template' => $template->name()]);
        }
    }

    /**
     * Gun/hafta/ay raporlarinda ayni yazar + donem (+ konu) icin ikinci rapor acilamaz.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertPeriodUnique(ReportTemplate $template, array $data, int $authorId, ?int $exceptId): void
    {
        if (! $template->periodMode()->isCalendarUnit() || ! filled($data['period_start'] ?? null)) {
            return;
        }

        $column = $template->subjectKind()->column();
        $existing = $this->queries->periodReport(
            $template->code(),
            $authorId,
            (string) $data['period_start'],
            $column,
            $column !== null ? ($data[$column] ?? null) : null,
            $exceptId,
        );

        if ($existing !== null) {
            throw DuplicatePeriodReportException::make(['no' => $existing->report_no]);
        }
    }

    /**
     * Pano kalemlerini veriden ayirir ve normalize eder; taslakta pano yoksa null.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>|null
     */
    private function extractItems(ReportTemplate $template, array &$data): ?array
    {
        $raw = $data['items'] ?? null;
        unset($data['items']);

        if (! $template->hasItems() || ! is_array($raw)) {
            return $template->hasItems() ? [] : null;
        }

        $items = [];
        $order = 0;

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            // Filament enum secenekli Select durumu enum nesnesi olarak da gonderebilir.
            $rawStatus = $row['status'] ?? null;
            $status = $rawStatus instanceof ReportItemStatus
                ? $rawStatus
                : (ReportItemStatus::tryFrom((string) ($rawStatus ?? '')) ?? ReportItemStatus::Planned);
            $hours = $row['work_hours'] ?? null;

            $item = [
                'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
                'sort_order' => $order++,
                'title' => mb_substr($title, 0, 200),
                'description' => filled($row['description'] ?? null) ? mb_substr(trim((string) $row['description']), 0, 1000) : null,
                'status' => $status->value,
                'project_id' => filled($row['project_id'] ?? null) ? (int) $row['project_id'] : null,
                'work_hours' => is_numeric($hours) ? round(max(0, (float) $hours), 2) : null,
                'due_on' => filled($row['due_on'] ?? null) ? Carbon::parse((string) $row['due_on'])->format('Y-m-d') : null,
                'carried_from_item_id' => filled($row['carried_from_item_id'] ?? null) ? (int) $row['carried_from_item_id'] : null,
            ];

            // Is panosundan dondurulan kalem kaynak kartini tasir (B36, D-115).
            if (SchemaReadiness::hasBatch('B36') && (array_key_exists('work_item_id', $row) || array_key_exists('is_late', $row))) {
                $item['work_item_id'] = filled($row['work_item_id'] ?? null) ? (int) $row['work_item_id'] : null;
                $item['is_late'] = (bool) ($row['is_late'] ?? false);
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Kalemleri kimlik koruyarak esitler: gelen id'li satir guncellenir,
     * id'siz satir eklenir, listede olmayan satir silinir.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(Report $report, array $items): void
    {
        $existing = $report->items()->get()->keyBy(fn (ReportItem $item): int => (int) $item->getKey());
        $kept = [];

        foreach ($items as $row) {
            $id = $row['id'] ?? null;
            unset($row['id']);

            /** @var ReportItem|null $item */
            $item = $id !== null ? $existing->get($id) : null;

            if ($item instanceof ReportItem) {
                $item->fill($row)->save();
            } else {
                $item = $report->items()->create($row);
            }

            $kept[] = (int) $item->getKey();
        }

        $report->items()->whereKeyNot($kept === [] ? [0] : $kept)->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveTitle(ReportTemplate $template, array $data): string
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title !== '') {
            return mb_substr($title, 0, 200);
        }

        $kind = $template->subjectKind();
        $column = $kind->column();
        $subject = $column !== null ? $this->queries->subject($kind, $data[$column] ?? null) : null;

        return $template->defaultTitle(
            $kind->labelFor($subject),
            filled($data['period_start'] ?? null) ? Carbon::parse((string) $data['period_start']) : null,
            filled($data['period_end'] ?? null) ? Carbon::parse((string) $data['period_end']) : null,
        );
    }

    private function resolveReviewer(ReportTemplate $template, Report $report): ?int
    {
        $author = $report->author;

        if (! $author instanceof Personnel) {
            return null;
        }

        $candidate = match ($template->reviewMode()) {
            ReportReviewMode::None => null,
            ReportReviewMode::LineManager => $author->currentManager() ?? $author->orgUnit?->manager,
            ReportReviewMode::OrgUnitManager => $author->orgUnit?->manager ?? $author->currentManager(),
        };

        if (! $candidate instanceof Personnel || $candidate->is($author) || ! $candidate->isReachable()) {
            return null;
        }

        return (int) $candidate->getKey();
    }

    /** KPI projeksiyonu: gonderimde yeniden uretilir. */
    private function writeMetrics(Report $report, ReportTemplate $template): void
    {
        $report->metrics()->delete();

        $items = $report->items instanceof Collection ? $report->items : new Collection;
        $now = Carbon::now('UTC');

        foreach ($template->metrics($report->payload ?? [], $items) as $code => $value) {
            $report->metrics()->create([
                'metric_code' => $code,
                'metric_value' => $value,
                'unit' => $template->metricUnit($code),
                'projected_at' => $now,
            ]);
        }
    }

    /**
     * @param  Collection<int, Personnel>  $recipients
     */
    private function notify(Report $report, Collection $recipients, string $event): void
    {
        $me = $this->actor->personnelId();
        $recipients = $recipients->reject(fn (Personnel $personnel): bool => $me !== null && (int) $personnel->getKey() === $me);

        if ($recipients->isEmpty()) {
            return;
        }

        $actions = [];

        try {
            $actions[] = Action::make('open')
                ->label(__('report.actions.open'))
                ->button()
                ->url(ReportResource::getUrl('view', ['record' => $report]))
                ->markAsRead();
        } catch (Throwable) {
            // Rota yoksa dugmesiz bildirim.
        }

        $this->notifier->send(
            $recipients,
            __('report.notifications.'.$event.'.title', ['no' => $report->report_no]),
            __('report.notifications.'.$event.'.body', [
                'title' => $report->title,
                'author' => $report->author?->full_name ?? '-',
                'reviewer' => $report->reviewer?->full_name ?? '-',
            ]),
            match ($event) {
                'approved' => Heroicon::OutlinedCheckCircle,
                'rejected' => Heroicon::OutlinedXCircle,
                'revision_required' => Heroicon::OutlinedArrowUturnLeft,
                default => Heroicon::OutlinedDocumentChartBar,
            },
            match ($event) {
                'approved' => 'success',
                'rejected' => 'danger',
                default => 'warning',
            },
            $actions,
        );
    }

    private function actorPersonnel(): Personnel
    {
        $id = $this->actor->personnelId();
        $personnel = $id !== null ? Personnel::query()->find($id) : null;

        if (! $personnel instanceof Personnel) {
            throw ActorRequiredException::make();
        }

        return $personnel;
    }
}
