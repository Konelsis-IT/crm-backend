<?php

declare(strict_types=1);

namespace App\Services\Report;

use Carbon\CarbonImmutable;
use App\Models\Report\RecurringWork;
use App\Enums\Platform\Feature;
use App\Enums\Report\WorkDiscipline;
use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemPriority;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Enums\Report\WorkItemSource;
use App\Enums\Report\WorkItemStatus;
use App\Enums\Report\WorkWaitingKind;
use App\Enums\WorkRequest\WorkRequestPriority;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\InvalidTransitionException;
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
use App\Models\Report\WorkItemFile;
use App\Models\Report\WorkItemNote;
use App\Models\Report\WorkSuggestionDismissal;
use App\Models\WorkRequest\WorkRequest;
use App\Query\Report\ReportQueries;
use App\Query\Report\WorkItemQueries;
use App\Reports\Templates\CoordinationBoardReportTemplate;
use App\Reports\Templates\DailyWorkReportTemplate;
use App\Reports\Templates\WeeklyWorkReportTemplate;
use App\Reports\Work\WorkCategoryCatalog;
use App\Services\AbstractService;
use App\Services\Document\FileObjectService;
use App\Services\Audit\ActivityInput;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Notification\PanelNotifier;
use App\Services\Numbering\YearlyCodeAllocator;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Services\WorkRequest\WorkRequestService;
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

    /** Toplu aktarim suruyor (import): baskasina is yazma kurali aranmaz. */
    private bool $importing = false;

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
        private readonly YearlyCodeAllocator $codes,
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
        $assignees = $this->assigneeIds($data, $me, null);

        return $this->transactions->run(fn (): WorkItem => $this->persistNew($attributes, $status, $now, $assignees));
    }

    /**
     * Aktarilan is (D-219: Is takip listesi, proje alt kirim tablosu, satin alma
     * takip listesi). create() ile ayni kurallar; ek olarak gecmis tarihli is
     * kapatilmis gunun raporuna "sonradan eklendi" diye eklenmez, beklenen
     * kisiye bildirim gitmez ve verilen notlar ayni islemde yazilir.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{body: string, source?: string|null, noted_at?: string|null}>  $notes
     */
    public function import(array $data, array $notes = []): WorkItem
    {
        $me = $this->actorPersonnel();
        // Toplu aktarim: kisi listesi yonetimin verdigi istir; baskasina is yazma kurali aranmaz.
        $this->importing = true;

        try {
            $attributes = $this->normalize($data, null, $me);
            $status = $this->initialStatus($data, $attributes, true);
            $this->assertWaiting($status, $attributes);
            $assignees = $this->assigneeIds($data, $me, null);
        } finally {
            $this->importing = false;
        }

        return $this->transactions->run(function () use ($attributes, $status, $assignees, $notes, $data): WorkItem {
            $item = $this->persistNew($attributes, $status, Carbon::now('UTC'), $assignees, quiet: true);

            // Kapali isin gercek kapanis gunu (Hareketler'deki tarih); yoksa is gunu.
            if (! $status->isOpen() && filled($data['closed_on'] ?? null)) {
                $closed = Carbon::parse((string) $data['closed_on'].' 18:00', DisplayTime::zone());
                $item->forceFill(['done_on' => $closed->format('Y-m-d'), 'completed_at' => $closed->copy()->timezone('UTC'), 'status_changed_at' => $closed->copy()->timezone('UTC')]);
                $this->saveWithoutVersion($item);
            }

            foreach ($notes as $note) {
                $this->writeNote($item, (string) $note['body'], $note['source'] ?? null, $note['noted_at'] ?? null);
            }

            return $item;
        });
    }

    /**
     * Ise tarihli not (D-219; kullanici 16 Ekim 2026: "Hareketler içinde yine
     * ilgili iş yoksa oluşturulup varsa da iş detaylarında not olarak
     * eklenebilmelidir. Bir iş için birden fazla notta eklenebilir.").
     */
    public function addNote(Model|int|string $record, string $body, ?string $source = null, ?string $notedAt = null): WorkItemNote
    {
        return $this->transactions->run(function () use ($record, $body, $source, $notedAt): WorkItemNote {
            /** @var WorkItem $item */
            $item = $this->lockForUpdate($record);

            return $this->writeNote($item, $body, $source, $notedAt);
        });
    }

    /**
     * Ise belgeler (D-219: "iş model yapımıza çoklu belge eklenebilme sistemi").
     * Gelen: 'local' diskteki gecici yukleme anahtari => ozgun ad. Ayni icerik
     * daha once yuklendiyse dosya tekrar saklanmaz (FileObjectService); ayni
     * belge ayni ise iki kez baglanmaz.
     *
     * @param  array<string, string|null>  $uploads
     */
    public function addFiles(Model|int|string $record, array $uploads): int
    {
        // Dosya ozeti islem acilmadan hesaplanir (buyuk dosya, D-127).
        $objects = [];

        foreach ($uploads as $path => $name) {
            $objects[] = app(FileObjectService::class)->createFromUpload((string) $path, is_string($name) ? $name : null);
        }

        return $this->transactions->run(function () use ($record, $objects): int {
            /** @var WorkItem $item */
            $item = $this->lockForUpdate($record);
            $order = (int) $item->files()->max('sort_order');
            $added = [];

            foreach ($objects as $object) {
                if ($item->files()->where('file_object_id', (int) $object->getKey())->exists()) {
                    continue;
                }

                WorkItemFile::query()->create([
                    'work_item_id' => (int) $item->getKey(),
                    'file_object_id' => (int) $object->getKey(),
                    'sort_order' => ++$order,
                ]);
                $added[] = (string) $object->original_name;
            }

            if ($added !== []) {
                $this->recordActivity($item, 'updated', ['belge_eklendi' => implode(', ', $added)]);
            }

            return count($added);
        });
    }

    /** Belge baglantisini kaldirir; dosya nesnesi kalir (baska kayitta kullanilabilir). */
    public function removeFile(WorkItemFile $file): void
    {
        $this->transactions->run(function () use ($file): void {
            /** @var WorkItem $item */
            $item = $this->lockForUpdate((int) $file->work_item_id);
            $name = (string) ($file->fileObject?->original_name ?? '');
            $file->delete();
            $this->recordActivity($item, 'updated', ['belge_kaldirildi' => $name]);
        });
    }

    /** Oncelik (D-219, dort seviye); Kritik eski kritik bayragiyla esit tutulur. */
    public function setPriority(Model|int|string $record, string $priority): WorkItem
    {
        $target = WorkItemPriority::tryFrom($priority) ?? throw RecordNotFoundException::make();

        return $this->transactions->run(function () use ($record, $target): WorkItem {
            /** @var WorkItem $item */
            $item = $this->lockForUpdate($record);
            $before = $item->priorityLevel();

            if ($before === $target && (bool) $item->is_critical === ($target === WorkItemPriority::Critical)) {
                return $item;
            }

            if (WorkItemQueries::extended()) {
                $item->priority = $target;
            }

            $item->is_critical = $target === WorkItemPriority::Critical;
            $this->saveWithoutVersion($item);
            $this->recordActivity($item, 'updated', ['oncelik' => ['onceki' => $before->getLabel(), 'yeni' => $target->getLabel()]]);

            return $item;
        });
    }

    /**
     * Talebe bagli is (D-216; kullanici 15 Ekim 2026: "Talep bir kişiye
     * atanınca o kişinin panosuna talebe bağlı bir iş otomatik düşsün; kişi
     * her seferinde öneriden çevirmek zorunda kalmasın."). Talep acilinca,
     * birimden ustlenilince, sorumlusu degisince, yonlendirilince ve onaydan
     * geri donunce cagrilir. Sorumlunun acik bagli isi yoksa acilir (talep eden
     * = talebi acan, bagli kayit = talep, son tarih = talebin son tarihi, Kritik
     * oncelikli talepte kritik); talep baskasina gectiyse eski sorumlunun
     * kendiliginden acilan isi Iptal olur. Talebi ustlenen (kabul eden) icin is
     * "Devam ediyor"a gecer. Kart yazma yetkisi aranmaz: isi sistem acar.
     */
    public function syncForWorkRequest(WorkRequest $request, bool $started = false): ?WorkItem
    {
        if (! $this->requestLinkEnabled()) {
            return null;
        }

        return $this->transactions->run(function () use ($request, $started): ?WorkItem {
            $assigneeId = $request->assignee_personnel_id !== null ? (int) $request->assignee_personnel_id : null;
            $active = $request->isOpen() && $assigneeId !== null;
            $current = null;

            foreach ($this->queries->requestItemsForUpdate((int) $request->getKey(), true) as $item) {
                if ($active && $current === null && (int) $item->personnel_id === $assigneeId) {
                    $current = $item;

                    continue;
                }

                $this->closeForRequest($item, WorkItemStatus::Blocked, __('work_item.request_link.moved', ['no' => $request->request_no]));
            }

            if (! $active) {
                return null;
            }

            if ($current === null) {
                return $this->createForRequest($request, (int) $assigneeId, $started);
            }

            if ($started && $current->status === WorkItemStatus::Planned) {
                $this->closeForRequest($current, WorkItemStatus::InProgress, null);
            }

            return $current;
        });
    }

    /**
     * Talep kapaninca (D-216) bagli acik isler de kapanir: tamamlanan /
     * onaya giden talepte Tamamlandi, reddedilen / iptal edilen talepte Iptal.
     */
    public function closeForWorkRequest(WorkRequest $request, WorkItemStatus $target): int
    {
        if (! $this->requestLinkEnabled() || $target->isOpen()) {
            return 0;
        }

        return $this->transactions->run(function () use ($request, $target): int {
            $count = 0;
            $note = __('work_item.request_link.closed', ['no' => $request->request_no, 'status' => $request->status->getLabel()]);

            foreach ($this->queries->requestItemsForUpdate((int) $request->getKey(), false) as $item) {
                $this->closeForRequest($item, $target, $note);
                $count++;
            }

            return $count;
        });
    }

    /**
     * Isi tamamlayan kisi bagli talebi de kapatabilir mi (D-216: "Talebi
     * tamamlandı olarak kapat?" sorusu): talep acik ve kisi talebi
     * tamamlayabiliyor (talep eden degil, muhatap / sorumlu).
     */
    public function canCloseLinkedRequest(WorkItem $item, ?Personnel $viewer): bool
    {
        $request = $this->linkedRequest($item);

        return $request instanceof WorkRequest
            && $viewer instanceof Personnel
            && $request->isOpen()
            && Gate::forUser($viewer)->allows('complete', $request);
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
            $assignees = $this->assigneeIds($data, $me, $item);
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

            if ($assignees !== null) {
                $this->syncAssignees($item, $assignees);
            }

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

            // D-216: duzenlemede Tamamlandi secilip "Talebi kapat" isaretlendiyse.
            if ($target === WorkItemStatus::Done && (bool) ($data['close_request'] ?? false)) {
                $this->closeLinkedRequest($item, null);
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

            // D-216: "Talebi tamamlandı olarak kapat?" sorusuna evet.
            if ($target === WorkItemStatus::Done && (bool) ($extra['close_request'] ?? false)) {
                $this->closeLinkedRequest($item, filled($extra['note'] ?? null) ? trim((string) $extra['note']) : null);
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

            // D-219: dort seviyeli oncelik kritik bayragiyla esit kalir.
            if (WorkItemQueries::extended()) {
                $item->priority = $critical ? WorkItemPriority::Critical : WorkItemPriority::Normal;
            }

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
     * Yeni kartin kaydi (elle, oneriden ya da talepten): durum anlari, is kodu,
     * hareket, beklenen kisiye bildirim, kapatilmis gune ek.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persistNew(array $attributes, WorkItemStatus $status, Carbon $now, ?array $assignees = null, bool $quiet = false): WorkItem
    {
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

        // Is kodu IS-YYYY-NNNN (B56, D-204): ayni islemde sayactan; elle yazilmaz.
        if (SchemaReadiness::hasBatch('B56')) {
            $item->forceFill(['item_no' => $this->codes->next(WorkItem::CODE_PREFIX)['formatted_code']]);
        }

        $item->save();

        if ($assignees !== null && $assignees !== []) {
            $this->syncAssignees($item, $assignees);
        }

        $this->recordActivity($item, 'created', $this->summary($item));

        if (! $quiet) {
            $this->notifyAwaited($item, null);
            $this->appendToClosedDay($item);
        }

        return $item;
    }

    /**
     * Diger sorumlular (D-219): ana sorumlu listede tutulmaz; cikan satir
     * silinir, yeni gelen yazan kisiyle eklenir.
     *
     * @param  list<int>  $ids
     */
    private function syncAssignees(WorkItem $item, array $ids): void
    {
        $ids = array_values(array_diff(array_unique(array_map('intval', $ids)), [(int) $item->personnel_id, 0]));
        $current = $item->assignees()->pluck('personnel.id')->map(fn ($id): int => (int) $id)->all();
        $remove = array_values(array_diff($current, $ids));
        $add = array_values(array_diff($ids, $current));

        if ($remove !== []) {
            $item->assignees()->detach($remove);
        }

        foreach ($add as $id) {
            $item->assignees()->attach($id, ['created_by_personnel_id' => $this->actor->personnelId()]);
        }

        $item->unsetRelation('assignees');
    }

    /**
     * Gelen diger sorumlular; anahtar yoksa ya da B57 yoksa null (dokunulmaz).
     * Baskasina is yazma kurali her kisi icin aranir.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>|null
     */
    private function assigneeIds(array $data, Personnel $me, ?WorkItem $item): ?array
    {
        if (! WorkItemQueries::extended() || ! array_key_exists('assignee_ids', $data)) {
            return null;
        }

        $raw = is_array($data['assignee_ids']) ? $data['assignee_ids'] : [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $raw), fn (int $id): bool => $id > 0)));
        $existing = $item !== null ? $item->assignees()->pluck('personnel.id')->map(fn ($id): int => (int) $id)->all() : [];

        foreach (array_diff($ids, $existing) as $id) {
            $this->assertAssignee($me, (int) $id);
        }

        return $ids;
    }

    private function writeNote(WorkItem $item, string $body, ?string $source, ?string $notedAt): WorkItemNote
    {
        $body = trim($body);

        if ($body === '') {
            throw RecordNotFoundException::make();
        }

        /** @var WorkItemNote $note */
        $note = WorkItemNote::query()->create([
            'work_item_id' => (int) $item->getKey(),
            'body' => mb_substr($body, 0, 5000),
            'source' => filled($source) ? mb_substr(Str::squish((string) $source), 0, 40) : null,
            'noted_at' => filled($notedAt) ? Carbon::parse((string) $notedAt, DisplayTime::zone())->timezone('UTC') : Carbon::now('UTC'),
        ]);

        $this->recordActivity($item, 'updated', array_filter([
            'not_eklendi' => Str::limit($note->body, 300, '…'),
            'kaynak' => $note->source,
        ], static fn ($value): bool => $value !== null && $value !== ''));

        return $note;
    }

    /** Talepten kendiliginden acilan is (D-216). */
    private function createForRequest(WorkRequest $request, int $assigneeId, bool $started): WorkItem
    {
        $zone = DisplayTime::zone();
        $unit = Personnel::query()->whereKey($assigneeId)->value('org_unit_id');
        $attributes = [
            'title' => mb_substr(Str::squish((string) $request->title), 0, 200),
            'personnel_id' => $assigneeId,
            'org_unit_id' => $unit !== null ? (int) $unit : null,
            'project_id' => $request->project_id !== null ? (int) $request->project_id : null,
            'is_critical' => $request->priority === WorkRequestPriority::Critical,
            // D-219: talebin onceligi isin onceligi olur (ayni dort seviye).
            ...(WorkItemQueries::extended() ? ['priority' => WorkItemPriority::tryFrom((string) $request->priority?->value)?->value ?? WorkItemPriority::Normal->value] : []),
            'due_on' => $request->due_on?->format('Y-m-d'),
            'link_kind' => WorkItemLinkKind::WorkRequest->value,
            'linked_work_request_id' => (int) $request->getKey(),
            'source' => WorkItemSource::Automatic->value,
            ...$this->workMoment([], null, $zone),
        ];

        // Talep eden (B37): talebi acan kisi; yonetim onceligi buna bakar.
        if (SchemaReadiness::hasBatch('B37') && $request->requester_personnel_id !== null) {
            $attributes['requester_kind'] = WorkWaitingKind::Personnel->value;
            $attributes['requester_personnel_id'] = (int) $request->requester_personnel_id;
        }

        return $this->persistNew($attributes, $started ? WorkItemStatus::InProgress : WorkItemStatus::Planned, Carbon::now('UTC'));
    }

    /**
     * Gorev ve sorumlulugun donem isi (D-219): sorumlunun panosuna, donemin
     * acilis gununde 09:00'da, Planlandi; son tarih donemin beklenen gunu.
     * Talep eden = tanimi yapan (Verdigim isler'de izlenir). Isi sistem acar;
     * kart yazma yetkisi aranmaz. Ayni donemin ikinci isi veritabaninda da
     * engellidir (recurring_work_id + period_key tekil).
     */
    public function openRecurring(RecurringWork $work, string $periodKey, CarbonImmutable $workOn, CarbonImmutable $due, string $periodLabel, WorkItemStatus $status = WorkItemStatus::Planned): WorkItem
    {
        return $this->transactions->run(function () use ($work, $periodKey, $workOn, $due, $periodLabel, $status): WorkItem {
            $priority = $work->priority instanceof WorkItemPriority ? $work->priority : WorkItemPriority::Normal;
            $attributes = [
                'title' => mb_substr(Str::squish((string) $work->title).' · '.$periodLabel, 0, 200),
                'personnel_id' => (int) $work->personnel_id,
                'org_unit_id' => $work->org_unit_id !== null ? (int) $work->org_unit_id : null,
                'project_id' => $work->project_id !== null ? (int) $work->project_id : null,
                'category_code' => $work->category_code,
                'is_critical' => $priority === WorkItemPriority::Critical,
                'priority' => $priority->value,
                'due_on' => $due->format('Y-m-d'),
                'note' => filled($work->description) ? mb_substr((string) $work->description, 0, 2000) : null,
                'source' => WorkItemSource::Automatic->value,
                'recurring_work_id' => (int) $work->getKey(),
                'period_key' => $periodKey,
                ...$this->workMoment(['work_on' => $workOn->format('Y-m-d'), 'work_time' => '09:00'], null, DisplayTime::zone()),
            ];

            if (SchemaReadiness::hasBatch('B37') && $work->created_by_personnel_id !== null && (int) $work->created_by_personnel_id !== (int) $work->personnel_id) {
                $attributes['requester_kind'] = WorkWaitingKind::Personnel->value;
                $attributes['requester_personnel_id'] = (int) $work->created_by_personnel_id;
            }

            return $this->persistNew($attributes, $status, Carbon::now('UTC'), quiet: true);
        });
    }

    /** Talebe bagli isin durumu talepten degisir; hareket kaydina talep notu yazilir. */
    private function closeForRequest(WorkItem $item, WorkItemStatus $target, ?string $note): void
    {
        $from = $item->status;

        if (! $this->transition($item, $target, Carbon::now('UTC'))) {
            return;
        }

        $item->sort_order = 0;
        $this->saveWithoutVersion($item);

        $this->recordActivity($item, 'status_changed', array_filter([
            'durum' => ['onceki' => $from?->getLabel(), 'yeni' => $target->getLabel()],
            'not' => $note,
        ], static fn ($value): bool => $value !== null && $value !== ''));
    }

    /**
     * "Talebi tamamlandı olarak kapat?" sorusuna evet (D-216): is tamamlaninca
     * bagli talep ayni islemde tamamlanir (onaya tabi talep onaya gider).
     */
    private function closeLinkedRequest(WorkItem $item, ?string $note): void
    {
        $request = $this->linkedRequest($item);

        if (! $request instanceof WorkRequest || ! $request->isOpen()) {
            return;
        }

        if (! $this->canCloseLinkedRequest($item, $this->actorPersonnel())) {
            throw InvalidTransitionException::make();
        }

        app(WorkRequestService::class)->complete($request, $note);
    }

    private function linkedRequest(WorkItem $item): ?WorkRequest
    {
        if (! $this->requestLinkEnabled() || $item->link_kind !== WorkItemLinkKind::WorkRequest || $item->linked_work_request_id === null) {
            return null;
        }

        return WorkRequest::query()->find((int) $item->linked_work_request_id);
    }

    private function requestLinkEnabled(): bool
    {
        return FeatureFlags::enabled(Feature::WorkRequestWorkItems) && SchemaReadiness::hasBatch('B36');
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

        // D-219 (B57): oncelik, Tum ekip, is grubu, aktarim alanlari.
        if (WorkItemQueries::extended()) {
            $attributes = [...$attributes, ...$this->extendedAttributes($data, $item)];
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
     * Dort seviyeli oncelik (Kritik = eski kritik bayragi), Tum ekip, is grubu;
     * aktarilan iste eski is numarasi ve tekrarli is donemi (yalniz ilk kayitta).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extendedAttributes(array $data, ?WorkItem $item): array
    {
        $attributes = [];

        if (array_key_exists('priority', $data) && filled($data['priority'])) {
            $priority = WorkItemPriority::tryFrom(self::rawValue($data['priority'])) ?? WorkItemPriority::Normal;
            $attributes['priority'] = $priority->value;
            $attributes['is_critical'] = $priority === WorkItemPriority::Critical;
        } elseif (array_key_exists('is_critical', $data)) {
            $current = $item?->priorityLevel() ?? WorkItemPriority::Normal;
            $attributes['priority'] = (bool) $data['is_critical']
                ? WorkItemPriority::Critical->value
                : ($current === WorkItemPriority::Critical ? WorkItemPriority::Normal->value : $current->value);
        }

        if (array_key_exists('for_everyone', $data)) {
            $attributes['for_everyone'] = (bool) $data['for_everyone'];
        }

        if (array_key_exists('discipline', $data)) {
            $attributes['discipline'] = WorkDiscipline::tryFrom(self::rawValue($data['discipline']))?->value;
        }

        if ($item === null) {
            if (filled($data['legacy_no'] ?? null)) {
                $attributes['legacy_no'] = mb_substr(trim((string) $data['legacy_no']), 0, 20);
            }

            if (filled($data['recurring_work_id'] ?? null) && filled($data['period_key'] ?? null)) {
                $attributes['recurring_work_id'] = (int) $data['recurring_work_id'];
                $attributes['period_key'] = mb_substr((string) $data['period_key'], 0, 16);
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
     * Formdan gelen secim degeri: Filament enum secenekli Select'te durum enum
     * nesnesi olarak gelir; (string) donusumu hata verir (canli isler/35/edit).
     */
    private static function rawValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (is_scalar($value) ? (string) $value : '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function waitingAttributes(array $data, ?WorkItem $item): array
    {
        $kind = WorkWaitingKind::tryFrom(self::rawValue($data['waiting_kind'] ?? null));

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
        $kind = WorkWaitingKind::tryFrom(self::rawValue($data['requester_kind'] ?? null));
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
        $kind = WorkItemLinkKind::tryFrom(self::rawValue($data['link_kind'] ?? null)) ?? WorkItemLinkKind::None;
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
    private function initialStatus(array $data, array $attributes, bool $imported = false): WorkItemStatus
    {
        $raw = $data['status'] ?? null;
        $status = $raw instanceof WorkItemStatus ? $raw : (WorkItemStatus::tryFrom((string) ($raw ?? '')) ?? WorkItemStatus::Planned);

        // Ileri tarih verilirse kart Planlandi sayilir ve o gun panoya duser.
        if (! $imported && $attributes['work_on'] > WorkItemQueries::today()->format('Y-m-d') && $status->isOpen()) {
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

        if ($this->importing) {
            return;
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
     * Kartlari donmus rapor kalemine cevirir. Kural tek yerdedir
     * (WorkItemPresenter::reportRows); D-167 ile Rapor yaz ekranindaki
     * gunluk / haftalik rapor onerisi de ayni satirlari kullanir.
     *
     * @param  Collection<int, WorkItem>  $cards
     * @return list<array<string, mixed>>
     */
    private function freezeItems(Collection $cards, bool $withOwner = false): array
    {
        return $this->presenter->reportRows($cards, $withOwner);
    }

    /**
     * @return array<string, mixed>
     */
    private function freezeRow(WorkItem $item, bool $withOwner = false): array
    {
        return $this->presenter->reportRow($item, $withOwner);
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
        $item->loadMissing(['personnel:id,full_name', 'project:id,name', 'parent:id,title', 'orgUnit:id,name', ...(WorkItemQueries::extended() ? ['assignees:id,full_name'] : [])]);
        $link = $item->link_kind !== null && $item->link_kind !== WorkItemLinkKind::None ? $this->presenter->link($item) : null;

        return array_filter([
            'kod' => $item->getAttribute('item_no'),
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
            'oncelik' => WorkItemQueries::extended() ? $item->priorityLevel()->getLabel() : null,
            'diger_sorumlular' => WorkItemQueries::extended() && $item->assignees->isNotEmpty() ? $item->assignees->pluck('full_name')->implode(', ') : null,
            'tum_ekip' => WorkItemQueries::extended() && (bool) $item->getAttribute('for_everyone') ? true : null,
            'is_grubu' => $item->getAttribute('discipline') instanceof WorkDiscipline ? $item->discipline->getLabel() : null,
            'eski_no' => $item->getAttribute('legacy_no'),
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
