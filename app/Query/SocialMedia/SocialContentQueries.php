<?php

declare(strict_types=1);

namespace App\Query\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Enums\SocialMedia\SocialContentStatus;
use App\Enums\SocialMedia\SocialMediaUsage;
use App\Enums\SocialMedia\SocialReminderStage;
use App\Exceptions\RecordNotFoundException;
use App\Models\Activity\PersonnelActivity;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialCategory;
use App\Models\SocialMedia\SocialComment;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentMedia;
use App\Models\SocialMedia\SocialProfile;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialClock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Sosyal medya iceriklerinin okuma sorgulari (B31, D-106): akis, ayrinti,
 * sayaclar, ajanda, pano listesi, hatirlatma adaylari ve gecmis.
 *
 * "Bugun" yalniz SocialClock'tan gelir. "Plan adayi" tanimi her yerde aynidir:
 * durum bekliyor / revize edilsin / onaylandi, paylasilmamis ve plan tarihi
 * dolu (applyPlannable).
 *
 * Akista durum suzgeci verilmezse arsiv gizlenir; arsiv yalniz `status =
 * archived` ile gelir. "Onayimi bekleyenler" cift goz kuralina uyar: izinli
 * kisi kendi icerigini gormez, tam yetkili hepsini gorur.
 */
final class SocialContentQueries
{
    /** Akis ve ajanda kartlarinin ihtiyac duydugu iliskiler (N+1 olmasin). */
    public const CARD_RELATIONS = [
        'category',
        'platforms',
        'createdBy.orgUnit',
        'media.fileObject',
        'media.posterFile',
        'media.previewFile',
    ];

    /** Pano listesinde "gecikti" olarak gosterilecek en eski gun sayisi. */
    public const UPCOMING_MISSED_DAYS = 14;

    /** Ajanda listelerinin her biri icin ust sinir. */
    public const AGENDA_LIMIT = 50;

    /** Ayrinti ekranindaki gecmis satiri ust siniri. */
    public const HISTORY_LIMIT = 200;

    public const SCOPES = ['all', 'mine', 'awaiting_approval', 'to_publish'];

    public const PUBLISHED_FILTERS = ['yes', 'no'];

    /**
     * Akis: suzgecli ve sayfali icerik kartlari.
     *
     * Suzgec anahtarlari (hepsi istege bagli): profile, status, type, platform,
     * category, creator, q, from, to (plan tarihi araligi, Y-m-d), scope
     * (all|mine|awaiting_approval|to_publish), published (yes|no), stage
     * (today|tomorrow|approaching|missed).
     *
     * @param  array<string, mixed>  $filters
     */
    public function feed(array $filters, Personnel $viewer, int $page = 1, int $perPage = 24): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = min(60, max(1, $perPage));

        if (! SchemaReadiness::hasBatch('B31')) {
            return new Paginator([], 0, $perPage, $page);
        }

        $viewerId = (int) $viewer->getKey();
        $query = $this->cardQuery($viewerId);

        if (filled($filters['profile'] ?? null)) {
            $query->where('profile_id', (int) $filters['profile']);
        }

        $status = SocialContentStatus::tryFrom((string) ($filters['status'] ?? ''));

        if ($status !== null) {
            $query->where('status', $status->value);
        } else {
            $query->where('status', '!=', SocialContentStatus::Archived->value);
        }

        if (filled($filters['type'] ?? null)) {
            $query->where('content_type', (string) $filters['type']);
        }

        if (filled($filters['category'] ?? null)) {
            $query->where('category_id', (int) $filters['category']);
        }

        if (filled($filters['creator'] ?? null)) {
            $query->where('created_by_personnel_id', (int) $filters['creator']);
        }

        if (filled($filters['platform'] ?? null)) {
            $platform = (string) $filters['platform'];
            $query->whereHas('platforms', fn (Builder $inner): Builder => $inner->where('platform', $platform));
        }

        if (filled($filters['from'] ?? null)) {
            $query->where('planned_on', '>=', substr((string) $filters['from'], 0, 10));
        }

        if (filled($filters['to'] ?? null)) {
            $query->where('planned_on', '<=', substr((string) $filters['to'], 0, 10));
        }

        $published = (string) ($filters['published'] ?? '');

        if ($published === 'yes') {
            $query->whereNotNull('published_at');
        } elseif ($published === 'no') {
            $query->whereNull('published_at');
        }

        $this->applySearch($query, $filters['q'] ?? null);

        $scope = (string) ($filters['scope'] ?? 'all');

        if ($scope === 'mine') {
            $query->where('created_by_personnel_id', $viewerId);
        } elseif ($scope === 'awaiting_approval') {
            $this->applyAwaitingApproval($query, $viewer);
        } elseif ($scope === 'to_publish') {
            $query->where('status', SocialContentStatus::Approved->value)->whereNull('published_at');
        }

        $stage = SocialReminderStage::tryFrom((string) ($filters['stage'] ?? ''));

        if ($stage !== null) {
            $this->applyStage($query, $stage);
        }

        // Plan odakli listelerde en yakin tarih ustte; genel akista en yeni ustte.
        if ($stage !== null || $scope === 'to_publish') {
            $query->orderByRaw('planned_on IS NULL')->orderBy('planned_on')->orderBy('planned_time')->orderByDesc('id');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /** Ayrinti ekrani icin butun iliskileriyle tek icerik; yoksa is hatasi. */
    public function findForDetail(int $id): SocialContent
    {
        /** @var SocialContent|null $content */
        $content = SocialContent::query()
            ->with([
                'profile',
                'category',
                'platforms',
                'createdBy.orgUnit',
                'decidedBy.orgUnit',
                'publishedBy.orgUnit',
                'urgentRequestedBy',
                'media.fileObject',
                'media.posterFile',
                'media.previewFile',
                'comments' => function (HasMany $comments): void {
                    $comments->orderBy('id');
                },
                'comments.createdBy.orgUnit',
                'comments.resolvedBy.orgUnit',
                'comments.media',
                'reactions' => function (HasMany $reactions): void {
                    $reactions->orderBy('id');
                },
                'reactions.personnel.orgUnit',
                'revisions' => function (HasMany $revisions): void {
                    $revisions->orderByDesc('revision_no');
                },
                'revisions.createdBy.orgUnit',
            ])
            ->whereKey($id)
            ->first();

        if ($content === null) {
            throw RecordNotFoundException::make();
        }

        return $content;
    }

    /**
     * Bir hesabin sayaclari (akis sekmeleri, plan rozetleri).
     *
     * `all` ve `published` arsivi saymaz (suzgecsiz akisla ayni kapsam).
     *
     * @return array{all: int, pending: int, approved: int, rejected: int, revision_requested: int, archived: int, published: int, approved_unpublished: int, today: int, tomorrow: int, approaching: int, missed: int, awaiting_my_approval: int}
     */
    public function countsFor(int $profileId, Personnel $viewer): array
    {
        $counts = [
            'all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'revision_requested' => 0, 'archived' => 0,
            'published' => 0, 'approved_unpublished' => 0, 'today' => 0, 'tomorrow' => 0, 'approaching' => 0,
            'missed' => 0, 'awaiting_my_approval' => 0,
        ];

        if (! SchemaReadiness::hasBatch('B31')) {
            return $counts;
        }

        $today = SocialClock::today();
        $todayDate = $today->format('Y-m-d');
        $tomorrowDate = $today->addDay()->format('Y-m-d');
        $approachingEnd = $today->addDays(SocialClock::approachingDays())->format('Y-m-d');

        $archived = SocialContentStatus::Archived->value;
        $pending = SocialContentStatus::Pending->value;
        $approved = SocialContentStatus::Approved->value;
        $plannable = SocialContentStatus::plannableValues();
        $marks = implode(', ', array_fill(0, count($plannable), '?'));
        $candidate = "status IN ({$marks}) AND published_at IS NULL AND planned_on IS NOT NULL";

        $mode = $this->approvalMode($viewer);
        $viewerId = (int) $viewer->getKey();

        $awaitingSql = match ($mode) {
            'all' => 'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END)',
            'others' => 'SUM(CASE WHEN status = ? AND (created_by_personnel_id IS NULL OR created_by_personnel_id <> ?) THEN 1 ELSE 0 END)',
            default => '0',
        };

        $awaitingBindings = match ($mode) {
            'all' => [$pending],
            'others' => [$pending, $viewerId],
            default => [],
        };

        $row = SocialContent::query()
            ->toBase()
            ->where('profile_id', $profileId)
            ->selectRaw(
                'SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END) AS all_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS approved_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS rejected_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS revision_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS archived_count, '
                .'SUM(CASE WHEN status <> ? AND published_at IS NOT NULL THEN 1 ELSE 0 END) AS published_count, '
                .'SUM(CASE WHEN status = ? AND published_at IS NULL THEN 1 ELSE 0 END) AS approved_unpublished_count, '
                ."SUM(CASE WHEN {$candidate} AND planned_on = ? THEN 1 ELSE 0 END) AS today_count, "
                ."SUM(CASE WHEN {$candidate} AND planned_on = ? THEN 1 ELSE 0 END) AS tomorrow_count, "
                ."SUM(CASE WHEN {$candidate} AND planned_on > ? AND planned_on <= ? THEN 1 ELSE 0 END) AS approaching_count, "
                ."SUM(CASE WHEN {$candidate} AND planned_on < ? THEN 1 ELSE 0 END) AS missed_count, "
                ."{$awaitingSql} AS awaiting_count",
                [
                    $archived,
                    $pending,
                    $approved,
                    SocialContentStatus::Rejected->value,
                    SocialContentStatus::RevisionRequested->value,
                    $archived,
                    $archived,
                    $approved,
                    ...$plannable, $todayDate,
                    ...$plannable, $tomorrowDate,
                    ...$plannable, $tomorrowDate, $approachingEnd,
                    ...$plannable, $todayDate,
                    ...$awaitingBindings,
                ],
            )
            ->first();

        if ($row === null) {
            return $counts;
        }

        return [
            'all' => (int) ($row->all_count ?? 0),
            'pending' => (int) ($row->pending_count ?? 0),
            'approved' => (int) ($row->approved_count ?? 0),
            'rejected' => (int) ($row->rejected_count ?? 0),
            'revision_requested' => (int) ($row->revision_count ?? 0),
            'archived' => (int) ($row->archived_count ?? 0),
            'published' => (int) ($row->published_count ?? 0),
            'approved_unpublished' => (int) ($row->approved_unpublished_count ?? 0),
            'today' => (int) ($row->today_count ?? 0),
            'tomorrow' => (int) ($row->tomorrow_count ?? 0),
            'approaching' => (int) ($row->approaching_count ?? 0),
            'missed' => (int) ($row->missed_count ?? 0),
            'awaiting_my_approval' => (int) ($row->awaiting_count ?? 0),
        ];
    }

    /**
     * Plan ajandasi: bugun, yarin, yaklasan, geciken ve tarihi yaklastigi halde
     * hala karar bekleyen icerikler (her liste en cok AGENDA_LIMIT kayit).
     *
     * @return array<string, Collection<int, SocialContent>>
     */
    public function agendaFor(int $profileId, Personnel $viewer): array
    {
        $agenda = [
            'today' => new Collection,
            'tomorrow' => new Collection,
            'approaching' => new Collection,
            'missed' => new Collection,
            'awaiting_approval' => new Collection,
        ];

        if (! SchemaReadiness::hasBatch('B31')) {
            return $agenda;
        }

        $viewerId = (int) $viewer->getKey();

        foreach (SocialReminderStage::cases() as $stage) {
            $query = $this->applyStage($this->cardQuery($viewerId)->where('profile_id', $profileId), $stage);

            $stage === SocialReminderStage::Missed
                ? $query->orderByDesc('planned_on')
                : $query->orderBy('planned_on');

            $agenda[$stage->value] = $query->orderBy('planned_time')->orderBy('id')->limit(self::AGENDA_LIMIT)->get();
        }

        $windowEnd = SocialClock::today()->addDays(SocialClock::approachingDays())->format('Y-m-d');

        $agenda['awaiting_approval'] = $this->cardQuery($viewerId)
            ->where('profile_id', $profileId)
            ->whereIn('status', [SocialContentStatus::Pending->value, SocialContentStatus::RevisionRequested->value])
            ->whereNull('published_at')
            ->whereNotNull('planned_on')
            ->where('planned_on', '<=', $windowEnd)
            ->orderBy('planned_on')
            ->orderBy('planned_time')
            ->orderBy('id')
            ->limit(self::AGENDA_LIMIT)
            ->get();

        return $agenda;
    }

    /**
     * Pano listesi (widget): yaklasan ve yeni geciken plan adaylari. Sorumlu
     * personel ve tam yetkili hepsini, digerleri yalniz kendi icerigini gorur.
     */
    public function upcomingFor(Personnel $viewer): Builder
    {
        $query = $this->upcomingWindow(
            SocialContent::query()->with(['profile', 'platforms', 'createdBy']),
        );

        $viewerId = (int) $viewer->getKey();
        $seesAll = app(RoleResolver::class)->hasFullAccess($viewer)
            || app(SocialResponsibilityQueries::class)->isResponsible($viewerId);

        if (! $seesAll) {
            $query->where('created_by_personnel_id', $viewerId);
        }

        return $query->orderBy('planned_on')->orderBy('planned_time')->orderBy('id');
    }

    /** Kisinin pano listesine girecek kendi icerigi var mi? */
    public function hasOwnUpcoming(int $personnelId): bool
    {
        if ($personnelId <= 0 || ! SchemaReadiness::hasBatch('B31')) {
            return false;
        }

        return $this->upcomingWindow(SocialContent::query())
            ->where('created_by_personnel_id', $personnelId)
            ->exists();
    }

    /**
     * Hatirlatma taramasinin adaylari: plan adayi olup tarihi "yaklasiyor"
     * penceresine girmis ya da gecmis icerikler. "Gecikti" hatirlatmasi bir kez
     * yapildigi icin anahtari zaten "<plan tarihi>:missed" olanlar gelmez.
     *
     * @return Collection<int, SocialContent>
     */
    public function reminderCandidates(): Collection
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            return new Collection;
        }

        // Bir gunluk pay: tarama elle baska bir "bugun" ile calistirilabilir.
        $windowEnd = SocialClock::today()->addDays(SocialClock::approachingDays() + 1)->format('Y-m-d');

        return $this->applyPlannable(SocialContent::query())
            ->with(['profile', 'platforms', 'createdBy'])
            ->where('planned_on', '<=', $windowEnd)
            ->where(function (Builder $inner): void {
                $inner->whereNull('reminder_key')
                    ->orWhereRaw("reminder_key <> CONCAT(DATE_FORMAT(planned_on, '%Y-%m-%d'), ?)", [':'.SocialReminderStage::Missed->value]);
            })
            ->orderBy('planned_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Icerik olusturmus kisiler (akistaki "hazirlayan" suzgeci).
     *
     * @return Collection<int, Personnel>
     */
    public function creators(): Collection
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            return new Collection;
        }

        return Personnel::query()
            ->with('orgUnit')
            ->whereIn('id', SocialContent::query()
                ->select('created_by_personnel_id')
                ->whereNotNull('created_by_personnel_id')
                ->distinct())
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Icerigin plan asamasi; plan adayi degilse (arsiv, ret, paylasilmis,
     * tarihsiz) null.
     */
    public function stageOf(SocialContent $c): ?SocialReminderStage
    {
        if ($c->planned_on === null || $c->isPublished() || ! in_array($c->status, SocialContentStatus::plannable(), true)) {
            return null;
        }

        return SocialClock::stageFor($c->planned_on);
    }

    /**
     * Acik (cozulmemis) isaret sayilari; anahtar isaretin cizildigi medya
     * satiridir (surum). Yorumlar yukluyse bellekten, degilse tek sorguyla.
     *
     * @return array<int, int>
     */
    public function openMarkCounts(SocialContent $c): array
    {
        if ($c->relationLoaded('comments')) {
            $counts = [];

            foreach ($c->comments as $comment) {
                if ($comment instanceof SocialComment && $comment->isOpenMark() && $comment->media_id !== null) {
                    $mediaId = (int) $comment->media_id;
                    $counts[$mediaId] = ($counts[$mediaId] ?? 0) + 1;
                }
            }

            return $counts;
        }

        return $c->comments()
            ->toBase()
            ->whereNotNull('anchor_shape')
            ->whereNotNull('media_id')
            ->whereNull('resolved_at')
            ->selectRaw('media_id, COUNT(*) AS aggregate')
            ->groupBy('media_id')
            ->pluck('aggregate', 'media_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /** Icerigin toplam acik isaret sayisi (kart rozetinde). */
    public function openMarkTotal(SocialContent $c): int
    {
        $preloaded = $c->getAttribute('open_mark_count');

        if ($preloaded !== null) {
            return (int) $preloaded;
        }

        return array_sum($this->openMarkCounts($c));
    }

    /**
     * Acil onay istegini engelleyen kosul: 'archived', 'published', 'status'
     * (karar beklemiyor), 'window' (plan tarihi yok ya da uzak), 'cooldown'
     * (yakin zamanda istendi) ya da engel yoksa null. Yetki burada denetlenmez.
     */
    public function urgentBlocker(SocialContent $c): ?string
    {
        if ($c->isArchived()) {
            return 'archived';
        }

        if ($c->isPublished()) {
            return 'published';
        }

        if (! ($c->status?->isAwaitingDecision() ?? false)) {
            return 'status';
        }

        if (! SocialClock::isWithinUrgentWindow($c->planned_on)) {
            return 'window';
        }

        $requestedAt = $c->urgent_requested_at;

        if ($requestedAt !== null && $requestedAt->copy()->addHours($this->urgentCooldownHours())->greaterThan(Carbon::now('UTC'))) {
            return 'cooldown';
        }

        return null;
    }

    /** Iki acil onay istegi arasindaki en kisa sure (saat). */
    public function urgentCooldownHours(): int
    {
        return max(0, (int) config('konelsis.social_media.urgent_cooldown_hours', 12));
    }

    /**
     * Icerigin gecmisi (Personel Hareketleri, en yeni ustte): icerigin kendi
     * hareketleri ile medya ve isaret hareketleri birlikte.
     *
     * @return Collection<int, PersonnelActivity>
     */
    public function historyFor(SocialContent $c, int $limit = self::HISTORY_LIMIT): Collection
    {
        if (! SchemaReadiness::hasBatch('B08')) {
            return new Collection;
        }

        $mediaIds = $this->relatedKeys($c, 'media');
        $commentIds = $this->relatedKeys($c, 'comments');

        return PersonnelActivity::query()
            ->with('personnel')
            ->where(function (Builder $outer) use ($c, $mediaIds, $commentIds): void {
                $outer->where(function (Builder $own) use ($c): void {
                    $own->where('subject_type', SocialContent::SUBJECT_TYPE)
                        ->where('subject_id', (string) $c->getKey());
                });

                if ($mediaIds !== []) {
                    $outer->orWhere(function (Builder $media) use ($mediaIds): void {
                        $media->where('subject_type', 'social_content_media')->whereIn('subject_id', $mediaIds);
                    });
                }

                if ($commentIds !== []) {
                    $outer->orWhere(function (Builder $comments) use ($commentIds): void {
                        $comments->where('subject_type', 'social_comment')->whereIn('subject_id', $commentIds);
                    });
                }
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Etkin hesaplar (baglantilari ve sahibiyle), sirali.
     *
     * @return Collection<int, SocialProfile>
     */
    public function profiles(): Collection
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            return new Collection;
        }

        return SocialProfile::query()
            ->with(['links', 'owner.orgUnit'])
            ->where('status', ActiveStatus::Active->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Butun kategoriler (pasifler dahil; istemci durumuna gore ayirir), sirali.
     *
     * @return Collection<int, SocialCategory>
     */
    public function categories(): Collection
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            return new Collection;
        }

        return SocialCategory::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Hesap var mi (etkin olsun olmasin)? */
    public function profileExists(int $profileId): bool
    {
        return $profileId > 0 && SocialProfile::query()->whereKey($profileId)->exists();
    }

    public function categoryExists(int $categoryId): bool
    {
        return $categoryId > 0 && SocialCategory::query()->whereKey($categoryId)->exists();
    }

    /** Icerigin galeride gorunen (cikarilmamis) kok medya sayisi; istenirse yalniz bir turde. */
    public function galleryRootCount(SocialContent $c, ?string $kind = null): int
    {
        $query = SocialContentMedia::query()
            ->where('content_id', (int) $c->getKey())
            ->where('usage', SocialMediaUsage::Gallery->value)
            ->whereNull('removed_at')
            ->whereNull('parent_media_id');

        if ($kind !== null) {
            $query->where('kind', $kind);
        }

        return $query->count();
    }

    /** Icerigin herhangi bir medya satiri var mi (hesap degisikligini kilitler)? */
    public function hasAnyMedia(SocialContent $c): bool
    {
        return SocialContentMedia::query()->where('content_id', (int) $c->getKey())->exists();
    }

    /**
     * Plan adayi kosulu: karar bekleyen ya da onayli, paylasilmamis ve plan
     * tarihi olan icerik.
     */
    public function applyPlannable(Builder $query): Builder
    {
        return $query
            ->whereIn('status', SocialContentStatus::plannableValues())
            ->whereNull('published_at')
            ->whereNotNull('planned_on');
    }

    /** Plan asamasi suzgeci (yalniz plan adaylari). */
    public function applyStage(Builder $query, SocialReminderStage $stage): Builder
    {
        $today = SocialClock::today();
        $todayDate = $today->format('Y-m-d');
        $tomorrowDate = $today->addDay()->format('Y-m-d');

        $this->applyPlannable($query);

        return match ($stage) {
            SocialReminderStage::Missed => $query->where('planned_on', '<', $todayDate),
            SocialReminderStage::Today => $query->where('planned_on', '=', $todayDate),
            SocialReminderStage::Tomorrow => $query->where('planned_on', '=', $tomorrowDate),
            SocialReminderStage::Approaching => $query
                ->where('planned_on', '>', $tomorrowDate)
                ->where('planned_on', '<=', $today->addDays(SocialClock::approachingDays())->format('Y-m-d')),
        };
    }

    /**
     * "Onayimi bekleyenler": bekleyen icerikler; izinli kisi kendi icerigini
     * goremez (cift goz), tam yetkili hepsini gorur, karar yetkisi olmayan
     * hicbirini gormez.
     */
    public function applyAwaitingApproval(Builder $query, Personnel $viewer): Builder
    {
        $mode = $this->approvalMode($viewer);

        if ($mode === 'none') {
            return $query->whereRaw('1 = 0');
        }

        $query->where('status', SocialContentStatus::Pending->value)->whereNull('published_at');

        if ($mode === 'others') {
            $viewerId = (int) $viewer->getKey();

            $query->where(function (Builder $inner) use ($viewerId): void {
                $inner->whereNull('created_by_personnel_id')
                    ->orWhere('created_by_personnel_id', '!=', $viewerId);
            });
        }

        return $query;
    }

    /**
     * Kisinin karar kapsami: 'all' (tam yetkili), 'others' (izinli; kendi
     * icerigi haric) ya da 'none'. Kural politikadan okunur: olusturani bos
     * bir ornek icerik uzerinde `approve` sorulur.
     */
    public function approvalMode(Personnel $viewer): string
    {
        if (app(RoleResolver::class)->hasFullAccess($viewer)) {
            return 'all';
        }

        return Gate::forUser($viewer)->allows('approve', $this->probe()) ? 'others' : 'none';
    }

    /**
     * Kayittan bagimsiz yetki sorulari icin bos ornek: bekliyor durumunda,
     * olusturani olmayan, kaydedilmemis icerik.
     */
    public function probe(): SocialContent
    {
        return (new SocialContent)->forceFill(['status' => SocialContentStatus::Pending->value]);
    }

    /** Kart sorgusu: iliskiler, acik isaret sayisi ve izleyenin tepkisi. */
    private function cardQuery(int $viewerId): Builder
    {
        return SocialContent::query()
            ->with(self::CARD_RELATIONS)
            ->with(['reactions' => function (HasMany $reactions) use ($viewerId): void {
                $reactions->where('personnel_id', $viewerId);
            }])
            ->withCount(['comments as open_mark_count' => function (Builder $comments): void {
                $comments->whereNotNull('anchor_shape')->whereNull('resolved_at');
            }]);
    }

    /** Pano penceresi: plan adayi, tarihi yaklasmis ya da son gunlerde gecmis. */
    private function upcomingWindow(Builder $query): Builder
    {
        $today = SocialClock::today();

        return $this->applyPlannable($query)
            ->where('planned_on', '>=', $today->subDays(self::UPCOMING_MISSED_DAYS)->format('Y-m-d'))
            ->where('planned_on', '<=', $today->addDays(SocialClock::approachingDays())->format('Y-m-d'));
    }

    /** Baslik, icerik numarasi, aciklama ve kisa metinde arama (LIKE kacisli). */
    private function applySearch(Builder $query, mixed $term): void
    {
        $term = trim((string) ($term ?? ''));

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes(mb_substr($term, 0, 100), '%_\\').'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('title', 'like', $like)
                ->orWhere('content_no', 'like', $like)
                ->orWhere('caption', 'like', $like)
                ->orWhere('body_text', 'like', $like);
        });
    }

    /**
     * Iliskideki kayitlarin metin kimlikleri (hareket tablosunda subject_id
     * metindir); iliski yukluyse bellekten okunur.
     *
     * @return list<string>
     */
    private function relatedKeys(SocialContent $c, string $relation): array
    {
        $keys = $c->relationLoaded($relation)
            ? $c->getRelation($relation)->modelKeys()
            : $c->{$relation}()->pluck('id')->all();

        return array_values(array_map(static fn (mixed $key): string => (string) $key, $keys));
    }
}
