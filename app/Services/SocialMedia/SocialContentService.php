<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\SocialMedia\SocialContentStatus;
use App\Enums\SocialMedia\SocialContentType;
use App\Enums\SocialMedia\SocialImageFormat;
use App\Enums\SocialMedia\SocialMediaKind;
use App\Enums\SocialMedia\SocialPlatform;
use App\Enums\SocialMedia\SocialReactionType;
use App\Exceptions\ActorRequiredException;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\RecordNotFoundException;
use App\Exceptions\SocialMedia\BodyTooLargeException;
use App\Exceptions\SocialMedia\ContentArchivedException;
use App\Exceptions\SocialMedia\ContentPublishedException;
use App\Exceptions\SocialMedia\DecisionNoteRequiredException;
use App\Exceptions\SocialMedia\EmptyContentException;
use App\Exceptions\SocialMedia\NotApprovedException;
use App\Exceptions\SocialMedia\UrgentCooldownException;
use App\Exceptions\SocialMedia\UrgentNoRecipientException;
use App\Exceptions\SocialMedia\UrgentNotAllowedException;
use App\Exceptions\StaleRecordException;
use App\Filament\Resources\SocialContents\SocialContentResource;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialCategory;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentPlatform;
use App\Models\SocialMedia\SocialContentRevision;
use App\Models\SocialMedia\SocialProfile;
use App\Models\SocialMedia\SocialReaction;
use App\Query\SocialMedia\SocialContentQueries;
use App\Query\SocialMedia\SocialResponsibilityQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Notification\PanelNotifier;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Sosyal medya icerigi yasam dongusu (B31, D-106).
 *
 *  olustur (bekliyor) -> onayla / reddet / revize iste -> paylasildi isaretle
 *  reddedilen / revize istenen icerik tekrar onaya gonderilir; her icerik
 *  arsive kalkar ve arsivden cikar. SILME YOKTUR.
 *
 * Kurallar:
 *  - Onayli icerigin metni, bicimi ya da medyasi degisirse icerik kendiliginden
 *    "bekliyor"a doner (requireReapproval) ve karar alanlari temizlenir.
 *  - Paylasildi olarak isaretlenmis icerik kilitlidir (yalniz arsive kalkar);
 *    arsivdeki icerik degistirilemez.
 *  - Metin degistiginde onceki hali SocialContentRevision olarak saklanir.
 *  - Sayaclar, hatirlatma anahtari ve acil onay damgasi yalniz writeSilently()
 *    ile yazilir: model olayi tetiklenmez, satir surumu ve "son degistiren"
 *    oynamaz.
 *  - Her adim Personel Hareketleri'ne Turkce anahtar ve etiketlerle yazilir;
 *    govde metni, kimlik ve sayac harekete konmaz. Begeni hareket uretmez.
 *  - Karar olusturana, acil onay istegi onaycilara zil bildirimi olarak gider.
 *
 * Yetki burada denetlenmez (politika + denetleyici); burasi durum kurallaridir.
 */
final class SocialContentService extends AbstractService
{
    /** writeSilently() ile yazilabilen sistem kolonlari. */
    public const SILENT_COLUMNS = [
        'media_count', 'comment_count', 'like_count', 'dislike_count',
        'reminder_key', 'urgent_requested_at', 'urgent_requested_by_personnel_id',
    ];

    /** Degistiginde onayli icerigi yeniden onaya dusuren alanlar. */
    private const REAPPROVAL_FIELDS = ['title', 'caption', 'body_text', 'body_html', 'video_url', 'image_format'];

    /** Degistiginde metin revizyonu yazilan alanlar. */
    private const TEXT_FIELDS = ['title', 'caption', 'body_text', 'body_html'];

    protected string $model = SocialContent::class;

    protected string $orderBy = 'created_at';

    protected string $orderDirection = 'desc';

    /** @var array<int, string> */
    private array $actorNames = [];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly SocialContentQueries $queries,
        private readonly SocialResponsibilityQueries $responsibility,
        private readonly SocialHtmlSanitizer $sanitizer,
        private readonly PanelNotifier $notifier,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Yeni icerik; durum her zaman "bekliyor"dur.
     *
     * @param  array<string, mixed>  $data  profile_id, content_type, title, caption?, body_text?, body_html?,
     *                                      category_id?, platforms[], image_format?, video_url?, planned_on?, planned_time?
     */
    public function create(array $data): Model
    {
        $this->actorId();

        $type = SocialContentType::tryFrom((string) ($data['content_type'] ?? ''));
        $profileId = (int) ($data['profile_id'] ?? 0);

        if ($type === null || ! $this->queries->profileExists($profileId)) {
            throw RecordNotFoundException::make();
        }

        $platforms = $this->normalizePlatforms($data['platforms'] ?? []);
        $attributes = $this->normalize($data, $type);
        $attributes['profile_id'] = $profileId;

        // Plan saati tarihsiz anlam tasimaz.
        if (($attributes['planned_on'] ?? null) === null) {
            $attributes['planned_time'] = null;
        }

        return $this->transactions->run(function () use ($attributes, $type, $platforms): SocialContent {
            $content = new SocialContent;
            $content->fill([
                ...$attributes,
                'content_no' => 'SM-TMP-'.bin2hex(random_bytes(6)),
                'content_type' => $type->value,
                'status' => SocialContentStatus::Pending->value,
            ]);
            $content->save();

            $content->forceFill(['content_no' => sprintf('SM-%06d', (int) $content->getKey())])->save();

            $this->syncPlatforms($content, $platforms);
            $content->load(['profile', 'category', 'platforms']);

            $this->recordActivity($content, 'created', $this->createdChanges($content));

            return $content->refresh();
        });
    }

    /**
     * Icerigi duzenler. Tur degismez; medyasi olan icerigin hesabi degismez.
     * Veride row_version varsa surum kontrolu uygulanir (uyusmazlik ->
     * StaleRecordException).
     *
     * @param  array<string, mixed>  $data  Yalniz gonderilen anahtarlar degisir.
     */
    public function update(Model|int|string $record, array $data): Model
    {
        $this->actorId();

        return $this->transactions->run(function () use ($record, $data): SocialContent {
            /** @var SocialContent $content */
            $content = $this->lockForUpdate($record);
            $this->assertEditable($content);

            $expected = array_key_exists('row_version', $data) ? (int) $data['row_version'] : null;

            if ($expected !== null && (int) $content->getAttribute('row_version') !== $expected) {
                throw StaleRecordException::make();
            }

            $before = $this->snapshot($content);

            $content->fill($this->prepare($data, $content));

            $expected !== null
                ? $this->lock->save($content, $expected)
                : $this->saveWithoutVersion($content);

            $after = $this->snapshot($content);
            $changed = array_keys(array_filter($before, fn (mixed $value, string $key): bool => $value !== $after[$key], ARRAY_FILTER_USE_BOTH));

            $revisionNo = array_intersect(self::TEXT_FIELDS, $changed) !== []
                ? $this->writeRevision($content, $before)
                : null;

            $platformChanges = array_key_exists('platforms', $data)
                ? $this->syncPlatforms($content, $this->normalizePlatforms($data['platforms']))
                : ['eklenen' => [], 'cikarilan' => []];

            if (array_intersect(self::REAPPROVAL_FIELDS, $changed) !== []) {
                $this->requireReapproval($content, 'content_edited');
            }

            if (in_array('planned_on', $changed, true)) {
                $this->writeSilently((int) $content->getKey(), ['reminder_key' => null]);
            }

            $summary = $this->updatedChanges($content, $before, $after, $changed, $revisionNo, $platformChanges);

            if ($summary !== []) {
                $this->recordActivity($content, 'updated', ['icerik_no' => $content->content_no, ...$summary]);
            }

            return $content->refresh();
        });
    }

    /** Silme yoktur; icerik arsive kaldirilir. */
    public function delete(Model|int|string $record): bool
    {
        throw InvalidTransitionException::make();
    }

    /**
     * Durum degisikligi. Hedef: approved, rejected, revision_requested,
     * archived, pending (yalniz ret / revizeden "tekrar onaya gonder") ya da
     * sozde hedef "unarchive".
     */
    public function changeStatus(SocialContent $c, string $target, ?string $note = null): SocialContent
    {
        if ($target === 'unarchive') {
            return $this->unarchive($c);
        }

        $to = SocialContentStatus::tryFrom($target);

        if ($to === null) {
            throw InvalidTransitionException::make();
        }

        $note = filled($note) ? trim((string) $note) : null;

        if ($to->requiresNote() && $note === null) {
            throw DecisionNoteRequiredException::make();
        }

        $me = $this->actorId();

        $content = $this->transactions->run(function () use ($c, $to, $note, $me): SocialContent {
            /** @var SocialContent $content */
            $content = $this->lockForUpdate($c);
            $from = $content->status;

            // Arsivden yalniz unarchive() ile cikilir.
            if ($from === SocialContentStatus::Archived || ! $from->canTransitionTo($to)) {
                throw InvalidTransitionException::make();
            }

            if ($content->isPublished() && $to !== SocialContentStatus::Archived) {
                throw ContentPublishedException::make();
            }

            // "Bekliyor"a elle yalniz ret / revizeden donulur; onaylidan donus
            // icerik degisince kendiliginden olur (requireReapproval).
            if ($to === SocialContentStatus::Pending && ! $from->canBeResubmitted()) {
                throw InvalidTransitionException::make();
            }

            if ($to === SocialContentStatus::Approved) {
                $this->assertNotEmpty($content);
            }

            if ($to->isDecision()) {
                $content->fill([
                    'decision_note' => $note,
                    'decided_by_personnel_id' => $me,
                    'decided_at' => Carbon::now('UTC'),
                ]);
            } elseif ($to === SocialContentStatus::Archived) {
                $content->fill(['status_before_archive' => $from->value]);
            } else {
                $content->fill($this->clearedDecision());
            }

            $content->fill(['status' => $to->value])->save();

            $this->recordActivity($content, $to === SocialContentStatus::Archived ? 'archived' : 'status_changed', array_filter([
                'icerik_no' => $content->content_no,
                'durum' => ['onceki' => $from->getLabel(), 'yeni' => $to->getLabel()],
                'gerekce' => $note,
            ], fn (mixed $value): bool => $value !== null && $value !== ''));

            return $content;
        });

        if ($to->isDecision()) {
            $this->notifyDecision($content, $to, $note);
        }

        return $content->refresh();
    }

    /** Arsivden cikarir: arsiv oncesi duruma, o bos ise "bekliyor"a doner. */
    public function unarchive(SocialContent $c): SocialContent
    {
        $this->actorId();

        return $this->transactions->run(function () use ($c): SocialContent {
            /** @var SocialContent $content */
            $content = $this->lockForUpdate($c);

            if (! $content->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $restored = $content->status_before_archive ?? SocialContentStatus::Pending;

            if ($restored === SocialContentStatus::Archived) {
                $restored = SocialContentStatus::Pending;
            }

            if ($restored === SocialContentStatus::Pending) {
                $content->fill($this->clearedDecision());
            }

            $content->fill(['status' => $restored->value, 'status_before_archive' => null])->save();

            $this->recordActivity($content, 'unarchived', [
                'icerik_no' => $content->content_no,
                'durum' => ['onceki' => SocialContentStatus::Archived->getLabel(), 'yeni' => $restored->getLabel()],
            ]);

            return $content->refresh();
        });
    }

    /**
     * Icerigi paylasildi olarak isaretler; yalniz onayli icerik isaretlenir.
     *
     * @param  array<string, string|null>  $urls  platform degeri => paylasim baglantisi
     */
    public function publish(SocialContent $c, array $urls, ?string $note = null): SocialContent
    {
        $me = $this->actorId();
        $note = filled($note) ? trim((string) $note) : null;

        return $this->transactions->run(function () use ($c, $urls, $note, $me): SocialContent {
            /** @var SocialContent $content */
            $content = $this->lockForUpdate($c);
            $this->assertEditable($content);

            if ($content->status !== SocialContentStatus::Approved) {
                throw NotApprovedException::make();
            }

            $content->fill([
                'published_at' => Carbon::now('UTC'),
                'published_by_personnel_id' => $me,
                'publish_note' => $note,
            ])->save();

            $labels = [];
            $links = [];

            /** @var SocialContentPlatform $row */
            foreach ($content->platforms()->orderBy('id')->get() as $row) {
                $labels[] = $row->platform->getLabel();
                $url = $urls[$row->platform->value] ?? null;

                if (filled($url)) {
                    $row->fill(['published_url' => trim((string) $url)])->save();
                }

                if ($row->hasPublishedUrl()) {
                    $links[] = $row->platform->getLabel().': '.$row->published_url;
                }
            }

            $this->recordActivity($content, 'published', array_filter([
                'icerik_no' => $content->content_no,
                'platformlar' => $labels === [] ? null : implode(', ', $labels),
                'paylasim_baglantilari' => $links === [] ? null : implode('; ', $links),
                'paylasim_notu' => $note,
            ], fn (mixed $value): bool => $value !== null && $value !== ''));

            return $content->refresh();
        });
    }

    /**
     * Paylasim isaretini geri alir (icerik yeniden duzenlenebilir olur).
     * Platformlara girilmis paylasim baglantilari saklanir; yeniden
     * isaretlerken hazir gelir.
     */
    public function unpublish(SocialContent $c): SocialContent
    {
        $this->actorId();

        return $this->transactions->run(function () use ($c): SocialContent {
            /** @var SocialContent $content */
            $content = $this->lockForUpdate($c);

            if ($content->isArchived()) {
                throw ContentArchivedException::make();
            }

            if (! $content->isPublished()) {
                throw InvalidTransitionException::make();
            }

            $content->fill([
                'published_at' => null,
                'published_by_personnel_id' => null,
                'publish_note' => null,
            ])->save();

            // Plan tarihi gecmis olabilir; hatirlatma yeniden degerlendirilsin.
            $this->writeSilently((int) $content->getKey(), ['reminder_key' => null]);

            $this->recordActivity($content, 'unpublished', ['icerik_no' => $content->content_no]);

            return $content->refresh();
        });
    }

    /**
     * Acil onay istegi: plan tarihi yakin ve icerik hala karar bekliyorsa
     * onaycilara zil bildirimi gonderir. En az bir bildirim teslim edilmeden
     * damga vurulmaz; bekleme suresi teslimden sonra baslar.
     *
     * @return int Bildirim gonderilen kisi sayisi.
     */
    public function requestUrgentApproval(SocialContent $c): int
    {
        $me = $this->actorId();

        return $this->transactions->run(function () use ($c, $me): int {
            /** @var SocialContent $content */
            $content = $this->lockForUpdate($c);

            match ($this->queries->urgentBlocker($content)) {
                'archived' => throw ContentArchivedException::make(),
                'published' => throw ContentPublishedException::make(),
                'status' => throw InvalidTransitionException::make(),
                'window' => throw UrgentNotAllowedException::make(['days' => SocialClock::urgentWindowDays()]),
                'cooldown' => throw UrgentCooldownException::make(['hours' => $this->queries->urgentCooldownHours()]),
                default => null,
            };

            $approvers = $this->responsibility->approvers($content)
                ->filter(fn (mixed $person): bool => $person instanceof Personnel
                    && $person->isReachable()
                    && (int) $person->getKey() !== $me
                    && ! $content->isCreatedBy((int) $person->getKey()))
                ->unique(fn (Personnel $person): int => (int) $person->getKey())
                ->values();

            if ($approvers->isEmpty()) {
                throw UrgentNoRecipientException::make();
            }

            $sent = $this->notifier->send(
                $approvers,
                $this->notificationText('urgent_approval', 'title', $content),
                $this->notificationText('urgent_approval', 'body', $content),
                Heroicon::OutlinedBolt,
                'danger',
                $this->openActions($content, 'review'),
            );

            if ($sent < 1) {
                throw UrgentNoRecipientException::make();
            }

            $this->writeSilently((int) $content->getKey(), [
                'urgent_requested_at' => Carbon::now('UTC'),
                'urgent_requested_by_personnel_id' => $me,
            ]);

            $this->recordActivity($content, 'urgent_requested', array_filter([
                'icerik_no' => $content->content_no,
                'plan_tarihi' => $content->planned_on?->format('d.m.Y'),
                'bildirilen' => $sent,
            ], fn (mixed $value): bool => $value !== null));

            return $sent;
        });
    }

    /**
     * Begeni / begenmeme / geri alma. Kisi basina tek satir tutulur; hacim
     * nedeniyle Personel Hareketleri'ne yazilmaz.
     */
    public function react(SocialContent $c, SocialReactionType $r): SocialContent
    {
        $me = $this->actorId();

        return $this->transactions->run(function () use ($c, $r, $me): SocialContent {
            /** @var SocialContent $content */
            $content = $this->lockForUpdate($c);

            if ($content->isArchived()) {
                throw ContentArchivedException::make();
            }

            $reaction = SocialReaction::query()->firstOrNew([
                'content_id' => (int) $content->getKey(),
                'personnel_id' => $me,
            ]);

            if ($reaction->exists || $r !== SocialReactionType::None) {
                $reaction->fill(['reaction' => $r->value])->save();
            }

            $this->recomputeCounters($content);

            return $content->refresh();
        });
    }

    /**
     * Sistem kolonlarini model olayi tetiklemeden yazar: satir surumu,
     * "son degistiren" ve updated_at oynamaz. Yalniz SILENT_COLUMNS kabul edilir.
     *
     * @param  array<string, mixed>  $values
     */
    public function writeSilently(int $contentId, array $values): void
    {
        $values = array_intersect_key($values, array_flip(self::SILENT_COLUMNS));

        if ($contentId <= 0 || $values === []) {
            return;
        }

        $this->transactions->run(function () use ($contentId, $values): void {
            SocialContent::query()->toBase()->where('id', $contentId)->update($values);
        });
    }

    /**
     * Sayaclari sayarak yeniden yazar: galerideki (cikarilmamis) kok medya,
     * butun yorumlar, begeni ve begenmeme. Verilen ornek de guncellenir.
     */
    public function recomputeCounters(SocialContent $c): void
    {
        $values = [
            'media_count' => $this->queries->galleryRootCount($c),
            'comment_count' => $c->comments()->count(),
            'like_count' => $c->reactions()->where('reaction', SocialReactionType::Like->value)->count(),
            'dislike_count' => $c->reactions()->where('reaction', SocialReactionType::Dislike->value)->count(),
        ];

        $this->writeSilently((int) $c->getKey(), $values);
        $this->reflect($c, $values);
    }

    /** Hatirlatma anahtarini ("<plan tarihi>:<asama>") yazar; tarama kendisi yazmaz. */
    public function markReminded(SocialContent $c, string $key): void
    {
        $values = ['reminder_key' => mb_substr(trim($key), 0, 40)];

        $this->writeSilently((int) $c->getKey(), $values);
        $this->reflect($c, $values);
    }

    /**
     * Onayli ve paylasilmamis icerik degistiginde "bekliyor"a dondurur ve karar
     * alanlarini temizler. Cagiranin transaction'i icinde, icerik satiri kilitliyken
     * calisir (icerik ve medya servisleri).
     *
     * @param  string  $reason  content_edited, media_added, media_variant, media_selected, media_removed,
     *                          media_restored, media_reordered, media_caption, media_poster
     */
    public function requireReapproval(SocialContent $c, string $reason): void
    {
        if ($c->status !== SocialContentStatus::Approved || $c->isPublished()) {
            return;
        }

        $this->transactions->run(function () use ($c, $reason): void {
            $c->fill([...$this->clearedDecision(), 'status' => SocialContentStatus::Pending->value])->save();

            $this->recordActivity($c, 'reapproval_required', [
                'icerik_no' => $c->content_no,
                'neden' => $this->reapprovalReason($reason),
                'durum' => [
                    'onceki' => SocialContentStatus::Approved->getLabel(),
                    'yeni' => SocialContentStatus::Pending->getLabel(),
                ],
            ]);
        });
    }

    /** Arsivdeki ya da paylasilmis icerik degistirilemez. */
    public function assertEditable(SocialContent $c): void
    {
        if ($c->isArchived()) {
            throw ContentArchivedException::make();
        }

        if ($c->isPublished()) {
            throw ContentPublishedException::make();
        }
    }

    /**
     * Guncellemede yalniz gonderilen anahtarlar duzenlenir; tur degismez,
     * sistem ve karar alanlari disaridan yazilamaz.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version']);

        if (! $record instanceof SocialContent) {
            return $data;
        }

        $attributes = $this->normalize($data, $record->content_type);

        // Plan saati tarihsiz anlam tasimaz.
        $plannedOn = array_key_exists('planned_on', $attributes) ? $attributes['planned_on'] : $record->planned_on;

        if ($plannedOn === null && (array_key_exists('planned_time', $attributes) || $record->planned_time !== null)) {
            $attributes['planned_time'] = null;
        }

        if (array_key_exists('profile_id', $data)) {
            $profileId = (int) $data['profile_id'];

            // Medyasi olan icerigin hesabi degismez (dosyalar o hesaba yuklendi).
            if ($profileId !== (int) $record->profile_id && ! $this->queries->hasAnyMedia($record)) {
                if (! $this->queries->profileExists($profileId)) {
                    throw RecordNotFoundException::make();
                }

                $attributes['profile_id'] = $profileId;
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        if (! $record instanceof SocialContent) {
            return [];
        }

        $no = (string) $record->content_no;

        // Numara kayittan sonra verilir; hareket ozeti kesin numarayi tasisin.
        if (str_starts_with($no, 'SM-TMP-')) {
            $no = sprintf('SM-%06d', (int) $record->getKey());
        }

        $platforms = $record->platforms
            ->map(fn (SocialContentPlatform $row): string => $row->platform->getLabel())
            ->implode(', ');

        return array_filter([
            'icerik_no' => $no,
            'baslik' => $record->title,
            'hesap' => $record->profile?->name,
            'tur' => $record->content_type?->getLabel(),
            'kategori' => $record->category?->name,
            'platformlar' => $platforms,
            'plan_tarihi' => $record->planned_on?->format('d.m.Y'),
            'plan_saati' => $record->planned_time,
            'durum' => $record->status?->getLabel(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Gelen veriyi ture gore duzenler; yalniz veride bulunan anahtarlar doner.
     * Ture uymayan alanlar bosaltilir: duz govde yalniz kisa metinde, zengin
     * govde yalniz uzun metin / blogda, bicim yalniz fotografta, video
     * baglantisi yalniz videoda tutulur.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, SocialContentType $type): array
    {
        $out = [];

        if (array_key_exists('title', $data)) {
            $out['title'] = mb_substr(trim((string) $data['title']), 0, 200);
        }

        if (array_key_exists('caption', $data)) {
            $out['caption'] = $this->text($data['caption'], (int) config('konelsis.social_media.caption_max', 5000));
        }

        if (array_key_exists('body_text', $data)) {
            $out['body_text'] = $type->hasPlainBody()
                ? $this->text($data['body_text'], (int) config('konelsis.social_media.short_text_hard_limit', 25000))
                : null;
        }

        if (array_key_exists('body_html', $data)) {
            $out['body_html'] = $type->hasRichBody() && is_string($data['body_html'])
                ? $this->sanitizer->sanitize($data['body_html'])
                : null;
        }

        if (array_key_exists('category_id', $data)) {
            $categoryId = filled($data['category_id']) ? (int) $data['category_id'] : null;

            if ($categoryId !== null && ! $this->queries->categoryExists($categoryId)) {
                throw RecordNotFoundException::make();
            }

            $out['category_id'] = $categoryId;
        }

        if (array_key_exists('image_format', $data)) {
            $out['image_format'] = $type === SocialContentType::Photo
                ? SocialImageFormat::tryFrom((string) ($data['image_format'] ?? ''))?->value
                : null;
        }

        if (array_key_exists('video_url', $data)) {
            $url = filled($data['video_url']) ? trim((string) $data['video_url']) : null;

            $out['video_url'] = $type === SocialContentType::Video && $url !== null && preg_match('#^https?://#i', $url) === 1
                ? mb_substr($url, 0, 500)
                : null;
        }

        if (array_key_exists('planned_on', $data)) {
            $out['planned_on'] = filled($data['planned_on']) ? substr((string) $data['planned_on'], 0, 10) : null;
        }

        if (array_key_exists('planned_time', $data)) {
            $time = filled($data['planned_time']) ? substr((string) $data['planned_time'], 0, 5) : null;

            $out['planned_time'] = $time !== null && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1 ? $time : null;
        }

        return $out;
    }

    /** Duz metni kirpar; bos ise null. Sinir asilirsa is hatasi. */
    private function text(mixed $value, int $max): ?string
    {
        $text = is_string($value) ? trim(str_replace(["\r\n", "\r"], "\n", $value)) : '';

        if ($text === '') {
            return null;
        }

        if ($max > 0 && mb_strlen($text) > $max) {
            throw BodyTooLargeException::make(['max' => $this->characterLimit($max)]);
        }

        return $text;
    }

    /** "25.000 karakter" bicimi; birim cevirisi yoksa yalniz sayi. */
    private function characterLimit(int $max): string
    {
        $number = number_format($max, 0, ',', '.');
        $key = 'social_content.ui.characters';
        $text = __($key, ['count' => $number]);

        return is_string($text) && $text !== $key ? $text : $number;
    }

    /**
     * @return list<SocialPlatform>
     */
    private function normalizePlatforms(mixed $platforms): array
    {
        $out = [];

        foreach (is_array($platforms) ? $platforms : [] as $value) {
            $platform = $value instanceof SocialPlatform ? $value : SocialPlatform::tryFrom((string) $value);

            if ($platform !== null) {
                $out[$platform->value] = $platform;
            }
        }

        return array_values($out);
    }

    /**
     * Platform satirlarini esitler: eksikler eklenir, listede olmayanlar
     * kaldirilir; paylasim baglantisi girilmis satir korunur.
     *
     * @param  list<SocialPlatform>  $platforms
     * @return array{eklenen: list<string>, cikarilan: list<string>}
     */
    private function syncPlatforms(SocialContent $content, array $platforms): array
    {
        /** @var Collection<int, SocialContentPlatform> $existing */
        $existing = $content->platforms()->orderBy('id')->get();
        $wanted = [];

        foreach ($platforms as $platform) {
            $wanted[$platform->value] = $platform;
        }

        $added = [];
        $removed = [];

        /** @var SocialContentPlatform $row */
        foreach ($existing as $row) {
            $value = $row->platform->value;

            if (isset($wanted[$value])) {
                unset($wanted[$value]);

                continue;
            }

            if ($row->hasPublishedUrl()) {
                continue;
            }

            // Baglanti tablosu esitlemesi; icerik, yorum ya da medya silinmez.
            $row->delete();
            $removed[] = $row->platform->getLabel();
        }

        foreach ($wanted as $platform) {
            $row = new SocialContentPlatform;
            $row->fill(['content_id' => (int) $content->getKey(), 'platform' => $platform->value])->save();
            $added[] = $platform->getLabel();
        }

        $content->unsetRelation('platforms');

        return ['eklenen' => $added, 'cikarilan' => $removed];
    }

    /**
     * Karsilastirma icin duz degerler (enum ve tarih metne cevrilir).
     *
     * @return array<string, mixed>
     */
    private function snapshot(SocialContent $content): array
    {
        return [
            'title' => $content->title,
            'caption' => $content->caption,
            'body_text' => $content->body_text,
            'body_html' => $content->body_html,
            'profile_id' => $content->profile_id !== null ? (int) $content->profile_id : null,
            'category_id' => $content->category_id !== null ? (int) $content->category_id : null,
            'image_format' => $content->image_format?->value,
            'video_url' => $content->video_url,
            'planned_on' => $content->planned_on?->format('Y-m-d'),
            'planned_time' => $content->planned_time,
        ];
    }

    /**
     * Metnin ONCEKI halini revizyon olarak saklar; revizyon numarasini doner.
     *
     * @param  array<string, mixed>  $before
     */
    private function writeRevision(SocialContent $content, array $before): int
    {
        $revisionNo = ((int) $content->revisions()->max('revision_no')) + 1;

        $revision = new SocialContentRevision;
        $revision->fill([
            'content_id' => (int) $content->getKey(),
            'revision_no' => $revisionNo,
            'title' => (string) ($before['title'] ?? ''),
            'caption' => $before['caption'] ?? null,
            'body_text' => $before['body_text'] ?? null,
            'body_html' => $before['body_html'] ?? null,
        ])->save();

        return $revisionNo;
    }

    /**
     * Duzenleme hareketi: Turkce anahtarlar ve etiket degerleri. Govde metni,
     * aciklama, kimlik ve sayac harekete yazilmaz.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $changed
     * @param  array{eklenen: list<string>, cikarilan: list<string>}  $platforms
     * @return array<string, mixed>
     */
    private function updatedChanges(SocialContent $content, array $before, array $after, array $changed, ?int $revisionNo, array $platforms): array
    {
        $summary = [];

        if (in_array('title', $changed, true)) {
            $summary['baslik'] = ['onceki' => $before['title'], 'yeni' => $after['title']];
        }

        if (in_array('profile_id', $changed, true)) {
            $summary['hesap'] = [
                'onceki' => $this->profileName($before['profile_id']),
                'yeni' => $this->profileName($after['profile_id']),
            ];
        }

        if (in_array('category_id', $changed, true)) {
            $summary['kategori'] = [
                'onceki' => $this->categoryName($before['category_id']),
                'yeni' => $this->categoryName($after['category_id']),
            ];
        }

        if ($platforms['eklenen'] !== [] || $platforms['cikarilan'] !== []) {
            $summary['platformlar'] = array_filter([
                'eklenen' => implode(', ', $platforms['eklenen']),
                'cikarilan' => implode(', ', $platforms['cikarilan']),
            ], fn (string $value): bool => $value !== '');
        }

        if (in_array('planned_on', $changed, true)) {
            $summary['plan_tarihi'] = [
                'onceki' => $this->displayDate($before['planned_on']),
                'yeni' => $this->displayDate($after['planned_on']),
            ];
        }

        if (in_array('planned_time', $changed, true)) {
            $summary['plan_saati'] = ['onceki' => $before['planned_time'], 'yeni' => $after['planned_time']];
        }

        if (in_array('image_format', $changed, true)) {
            $summary['format'] = [
                'onceki' => SocialImageFormat::tryFrom((string) $before['image_format'])?->getLabel(),
                'yeni' => SocialImageFormat::tryFrom((string) $after['image_format'])?->getLabel(),
            ];
        }

        if (in_array('video_url', $changed, true)) {
            $summary['video_baglantisi'] = ['onceki' => $before['video_url'], 'yeni' => $after['video_url']];
        }

        if ($revisionNo !== null) {
            $summary['metin'] = ['revizyon' => $revisionNo];
        }

        return $summary;
    }

    /** Onay icin icerik bos olamaz: fotografta gorsel, videoda dosya ya da baglanti, metinde govde. */
    private function assertNotEmpty(SocialContent $content): void
    {
        $filled = match ($content->content_type) {
            SocialContentType::Photo => $this->queries->galleryRootCount($content) > 0,
            SocialContentType::Video => filled($content->video_url)
                || $this->queries->galleryRootCount($content, SocialMediaKind::Video->value) > 0,
            SocialContentType::ShortText => trim((string) $content->body_text) !== '',
            SocialContentType::LongText, SocialContentType::Blog => ! $this->sanitizer->isBlank($content->body_html),
        };

        if (! $filled) {
            throw EmptyContentException::make();
        }
    }

    /**
     * "Bekliyor"a her donuste temizlenen karar alanlari.
     *
     * @return array<string, null>
     */
    private function clearedDecision(): array
    {
        return ['decision_note' => null, 'decided_by_personnel_id' => null, 'decided_at' => null];
    }

    /** Yeniden onay nedeninin etiketi; ceviri yoksa genel neden. */
    private function reapprovalReason(string $reason): string
    {
        foreach (['social_content.reapproval_reasons.'.$reason, 'social_content.reapproval_reasons.content_edited'] as $key) {
            $label = __($key);

            if (is_string($label) && $label !== $key) {
                return $label;
            }
        }

        return SocialContentStatus::Pending->getLabel();
    }

    /** Karar bildirimi: icerigi olusturana (karari kendisi vermediyse). */
    private function notifyDecision(SocialContent $content, SocialContentStatus $to, ?string $note): void
    {
        $creator = $content->createdBy;
        $me = $this->actor->personnelId();

        if (! $creator instanceof Personnel || ! $creator->isReachable() || ($me !== null && (int) $creator->getKey() === $me)) {
            return;
        }

        $event = match ($to) {
            SocialContentStatus::Approved => 'decision_approved',
            SocialContentStatus::Rejected => 'decision_rejected',
            default => 'decision_revision',
        };

        $this->notifier->send(
            [$creator],
            $this->notificationText($event, 'title', $content, $note),
            $this->notificationText($event, 'body', $content, $note),
            match ($to) {
                SocialContentStatus::Approved => Heroicon::OutlinedCheckCircle,
                SocialContentStatus::Rejected => Heroicon::OutlinedXCircle,
                default => Heroicon::OutlinedArrowPath,
            },
            match ($to) {
                SocialContentStatus::Approved => 'success',
                SocialContentStatus::Rejected => 'danger',
                default => 'warning',
            },
            $this->openActions($content, 'open'),
        );
    }

    /** Bildirim metni (lang social_content.notifications.<olay>.<title|body>). */
    private function notificationText(string $event, string $part, SocialContent $content, ?string $note = null): string
    {
        $actor = $this->actorName();

        return (string) __('social_content.notifications.'.$event.'.'.$part, [
            'no' => (string) $content->content_no,
            'title' => (string) $content->title,
            'note' => filled($note) ? (string) $note : '-',
            'actor' => filled($actor) ? (string) $actor : (string) __('activity.system'),
            'date' => $content->planned_on?->format('d.m.Y') ?? '-',
        ]);
    }

    /**
     * Bildirimdeki dugme: modul sayfasini bu icerik acik olarak acar.
     *
     * @return list<Action>
     */
    private function openActions(SocialContent $content, string $label): array
    {
        try {
            return [
                Action::make('open')
                    ->label(__('social_content.notifications.actions.'.$label))
                    ->button()
                    ->url(SocialContentResource::getUrl('index', ['icerik' => (int) $content->getKey()]))
                    ->markAsRead(),
            ];
        } catch (Throwable) {
            // Rota ya da kaynak yoksa dugmesiz bildirim.
            return [];
        }
    }

    /**
     * Sessizce yazilan degerleri verilen ornege de yansitir (kirli saymadan).
     *
     * @param  array<string, mixed>  $values
     */
    private function reflect(SocialContent $content, array $values): void
    {
        foreach ($values as $attribute => $value) {
            $content->setAttribute($attribute, $value);
            $content->syncOriginalAttribute($attribute);
        }
    }

    private function profileName(mixed $profileId): ?string
    {
        return $profileId === null ? null : SocialProfile::query()->whereKey((int) $profileId)->value('name');
    }

    private function categoryName(mixed $categoryId): ?string
    {
        return $categoryId === null ? null : SocialCategory::query()->whereKey((int) $categoryId)->value('name');
    }

    private function displayDate(mixed $date): ?string
    {
        return filled($date) ? Carbon::parse((string) $date)->format('d.m.Y') : null;
    }

    /** Islemi yapan kisinin adi (bildirim metni icin); istek basina bir kez okunur. */
    private function actorName(): ?string
    {
        $me = $this->actor->personnelId();

        if ($me === null) {
            return null;
        }

        return $this->actorNames[$me] ??= (string) Personnel::query()->whereKey($me)->value('full_name');
    }

    private function actorId(): int
    {
        $id = $this->actor->personnelId();

        if ($id === null) {
            throw ActorRequiredException::make();
        }

        return $id;
    }
}
