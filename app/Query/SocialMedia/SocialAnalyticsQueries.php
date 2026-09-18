<?php

declare(strict_types=1);

namespace App\Query\SocialMedia;

use App\Enums\Document\FileObjectStatus;
use App\Enums\Shared\ActiveStatus;
use App\Enums\SocialMedia\SocialContentStatus;
use App\Enums\SocialMedia\SocialContentType;
use App\Enums\SocialMedia\SocialMediaKind;
use App\Enums\SocialMedia\SocialPlatform;
use App\Enums\SocialMedia\SocialProfileKind;
use App\Enums\SocialMedia\SocialWatchKind;
use App\Models\Document\FileObject;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialCategory;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentMedia;
use App\Models\SocialMedia\SocialContentPlatform;
use App\Models\SocialMedia\SocialMetricEntry;
use App\Models\SocialMedia\SocialProfile;
use App\Models\SocialMedia\SocialSpecialDay;
use App\Models\SocialMedia\SocialWatchAccount;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialClock;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Sosyal Medya plan, analiz, depolama ve ayar okuma sorgulari (B31, D-106).
 *
 * - calendar(): ayin gunleri, o gune planli icerikler ve ozel gunler
 *   (`covered` = o gun icin icerik var mi).
 * - analytics(): donem gostergeleri, dagilimlar, atlanan listeler, en cok
 *   paylasan kisiler, takipci egrileri ve platform istatistik ozetleri.
 * - storage(): modulun disk kullanimi + sunucudaki bos alan.
 * - Ayar ekranlarinin okuma listeleri (kategori, ozel gun, izlenen hesap,
 *   istatistik girisi).
 *
 * "Bugun" yalniz SocialClock'tan alinir. Plan tarihi takvim gunudur; paylasim
 * ani (published_at) UTC saklanir ve gun / ay hesabinda kurum saatine cevrilir.
 *
 * Donen diziler JSON'a hazirdir; yalniz model tasiyan alanlar
 * (calendar.days[].contents, analytics.missed_planned_list,
 * analytics.top_creators[].person) denetleyicide sunum sinifindan gecirilir.
 * Burada yazma islemi yoktur.
 */
final class SocialAnalyticsQueries
{
    /** Kart sunumunun dokundugu iliskiler (N+1 onlemi); akis sorgulariyla ayni liste. */
    private const CARD_RELATIONS = SocialContentQueries::CARD_RELATIONS;

    /** Listelerde en fazla satir. */
    private const LIST_LIMIT = 50;

    /** Bir takvim ayinda okunacak en fazla icerik. */
    private const CALENDAR_LIMIT = 1000;

    /** "En cok paylasanlar" listesinin boyu. */
    private const TOP_CREATORS = 8;

    /** Toplanan (donem icinde biriken) olcum kolonlari; takipci anlik degerdir, toplanmaz. */
    private const SUMMED_METRICS = [
        'posts_count', 'impressions', 'reach', 'engagements', 'profile_visits', 'link_clicks', 'video_views',
    ];

    /** @var array<string, mixed>|null */
    private ?array $storageMemo = null;

    // ------------------------------------------------------------------
    // Takvim
    // ------------------------------------------------------------------

    /**
     * Ayin butun gunleri; her gunde planli icerikler ve ozel gunler.
     *
     * `contents` SocialContent modelleridir (denetleyici cardLite'a cevirir);
     * arsivdeki icerik takvime girmez. `special_days[].covered`: gecmis gunde
     * o gune ait PAYLASILMIS icerik, bugun ve sonrasinda paylasilmis ya da
     * plandaki (arsiv / ret disi) icerik varsa true.
     *
     * @return array{month: string, today: string, days: list<array{date: string, contents: list<SocialContent>, special_days: list<array{id: int, name: string, note: ?string, covered: bool}>}>}
     */
    public function calendar(int $profileId, string $month): array
    {
        $first = $this->monthStart($month);
        $last = $first->endOfMonth()->startOfDay();

        $days = [];

        for ($day = $first; $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
            $days[$day->format('Y-m-d')] = ['date' => $day->format('Y-m-d'), 'contents' => [], 'special_days' => []];
        }

        if (! SchemaReadiness::hasBatch('B31')) {
            return ['month' => $first->format('Y-m'), 'today' => SocialClock::todayString(), 'days' => array_values($days)];
        }

        $contents = SocialContent::query()
            ->with(self::CARD_RELATIONS)
            ->where('profile_id', $profileId)
            ->whereBetween('planned_on', [$first->format('Y-m-d'), $last->format('Y-m-d')])
            ->where('status', '!=', SocialContentStatus::Archived->value)
            ->orderBy('planned_on')
            ->orderByRaw('planned_time is null')
            ->orderBy('planned_time')
            ->orderBy('id')
            ->limit(self::CALENDAR_LIMIT)
            ->get();

        foreach ($contents as $content) {
            $date = $content->planned_on?->format('Y-m-d');

            if ($date !== null && isset($days[$date])) {
                $days[$date]['contents'][] = $content;
            }
        }

        $coverage = $this->coverage($profileId, $first, $last);

        foreach ($this->occurrences($profileId, $first, $last) as $occurrence) {
            $date = $occurrence['date'];

            if (! isset($days[$date])) {
                continue;
            }

            $days[$date]['special_days'][] = [
                'id' => (int) $occurrence['day']->getKey(),
                'name' => (string) $occurrence['day']->name,
                'note' => $occurrence['day']->note,
                'covered' => $this->isCovered($date, $coverage),
            ];
        }

        return ['month' => $first->format('Y-m'), 'today' => SocialClock::todayString(), 'days' => array_values($days)];
    }

    // ------------------------------------------------------------------
    // Analiz
    // ------------------------------------------------------------------

    /**
     * Donem analizi (SPEC 8 + F10).
     *
     * - published: paylasim ani donem icinde olan icerikler.
     * - planned: plan tarihi donemde olan, paylasilmis ya da halen gecerli
     *   (arsiv / ret disi) icerikler.
     * - missed_planned: plan tarihi donemde VE bugunden once, paylasilmamis,
     *   arsiv / ret disi.
     * - pending / revision_requested / approved_unpublished / rejected: hesabin
     *   SU ANKI durumu (doneme bagli degildir).
     * - avg_approval_hours: donemde onaylanan iceriklerin olusturma -> onay suresi.
     * - Ozel gun "atlandi": gun gecmis ve o gune ait paylasilmis icerik yok;
     *   bugunun ozel gunu, bugune planli gecerli icerik varsa atlanmis sayilmaz.
     *
     * `missed_planned_list` SocialContent, `top_creators[].person` Personnel
     * modeli tasir; denetleyici sunum bicimine cevirir.
     *
     * @return array<string, mixed>
     */
    public function analytics(int $profileId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $fromDay = SocialClock::date($from);
        $toDay = SocialClock::date($to);

        if ($toDay->lessThan($fromDay)) {
            [$fromDay, $toDay] = [$toDay, $fromDay];
        }

        $result = $this->emptyAnalytics($fromDay, $toDay);

        if (! SchemaReadiness::hasBatch('B31')) {
            return $result;
        }

        $fromDate = $fromDay->format('Y-m-d');
        $toDate = $toDay->format('Y-m-d');
        $today = SocialClock::todayString();

        // Paylasilanlar (donem icinde)
        $published = $this->publishedRows($profileId, $fromDay, $toDay);

        // Planlananlar ve atlanan planli paylasimlar
        $planned = SocialContent::query()
            ->where('profile_id', $profileId)
            ->whereBetween('planned_on', [$fromDate, $toDate])
            ->where(function (Builder $query): void {
                $query->whereNotNull('published_at')
                    ->orWhereNotIn('status', $this->deadStatuses());
            })
            ->count();

        $missedDates = $this->missedPlannedQuery($profileId, $fromDate, $toDate, $today)
            ->toBase()
            ->pluck('planned_on')
            ->map(fn ($value): string => substr((string) $value, 0, 10))
            ->all();

        $missedList = $this->missedPlannedQuery($profileId, $fromDate, $toDate, $today)
            ->with(self::CARD_RELATIONS)
            ->orderByDesc('planned_on')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->all();

        // Ozel gunler
        $occurrences = $this->occurrences($profileId, $fromDay, $toDay);
        $coverage = $this->coverage($profileId, $fromDay, $toDay);
        $missedSpecial = [];

        foreach ($occurrences as $occurrence) {
            if ($occurrence['date'] > $today || $this->isCovered($occurrence['date'], $coverage)) {
                continue;
            }

            $missedSpecial[] = ['date' => $occurrence['date'], 'name' => (string) $occurrence['day']->name];
        }

        usort($missedSpecial, fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        $snapshot = $this->statusSnapshot($profileId);
        $entries = $this->overlappingEntries($profileId, $fromDate, $toDate);

        $result['kpis'] = [
            'published' => $published->count(),
            'planned' => $planned,
            'missed_planned' => count($missedDates),
            'special_days' => count($occurrences),
            'missed_special_days' => count($missedSpecial),
            'pending' => $snapshot['pending'],
            'revision_requested' => $snapshot['revision_requested'],
            'approved_unpublished' => $snapshot['approved_unpublished'],
            'rejected' => $snapshot['rejected'],
            'avg_approval_hours' => $this->averageApprovalHours($profileId, $fromDay, $toDay),
        ];
        $result['special_days_defined'] = $this->hasActiveSpecialDays($profileId);
        $result['by_platform'] = $this->publishedByPlatform($profileId, $fromDay, $toDay);
        $result['by_type'] = $this->publishedByType($published);
        $result['by_month'] = $this->byMonth($fromDay, $toDay, $published, $missedDates);
        $result['missed_special_list'] = array_slice($missedSpecial, 0, self::LIST_LIMIT);
        $result['missed_planned_list'] = $missedList;
        $result['top_creators'] = $this->topCreators($published);
        $result['followers'] = $this->followerSeries($entries);
        $result['metrics_summary'] = $this->metricsSummary($entries, $this->followerBaselines($profileId, $fromDate));

        return $result;
    }

    // ------------------------------------------------------------------
    // Depolama
    // ------------------------------------------------------------------

    /**
     * Modulun disk kullanimi (F6). Bayt toplamlari `file_objects` satirlari
     * uzerinden alinir; ayni dosya (sha256 tekillestirme) kac medya satirinda
     * gecerse gecsin BIR kez sayilir. Galeriden cikarilan gruplar, surumler,
     * metin ici gorseller, kapak kareleri, onizlemeler ve kucuk gorseller
     * dahildir: uygulama dosya silmez, alan geri kazanilmaz.
     *
     * Bos / toplam alan storage_path() bolumunden okunur; okunamazsa null.
     *
     * @return array<string, mixed>
     */
    public function storage(): array
    {
        if ($this->storageMemo !== null) {
            return $this->storageMemo;
        }

        $video = 0;
        $image = 0;
        $other = 0;
        $removed = 0;

        if (SchemaReadiness::hasBatch('B31') && SchemaReadiness::hasBatch('B06')) {
            $video = $this->sumBytes(fn (Builder $query) => $query->whereIn('id', $this->mediaFileIds(SocialMediaKind::Video)));

            $image = $this->sumBytes(function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->whereIn('id', $this->mediaFileIds(SocialMediaKind::Image))
                        ->orWhereIn('id', $this->mediaColumnIds('poster_file_object_id'))
                        ->orWhereIn('id', $this->mediaColumnIds('preview_file_object_id'))
                        ->orWhereIn('derived_from_file_object_id', $this->mediaFileIds(SocialMediaKind::Image))
                        ->orWhereIn('derived_from_file_object_id', $this->mediaColumnIds('poster_file_object_id'));
                })->whereNotIn('id', $this->mediaFileIds(SocialMediaKind::Video));
            });

            $other = $this->sumBytes(function (Builder $query): void {
                $query->whereIn('id', SocialMetricEntry::query()->select('file_object_id')->whereNotNull('file_object_id'))
                    ->whereNotIn('id', SocialContentMedia::query()->select('file_object_id'))
                    ->whereNotIn('id', $this->mediaColumnIds('poster_file_object_id'))
                    ->whereNotIn('id', $this->mediaColumnIds('preview_file_object_id'));
            });

            $removed = $this->sumBytes(function (Builder $query): void {
                $query->whereIn('id', SocialContentMedia::query()->select('file_object_id')->whereNotNull('removed_at'))
                    ->whereNotIn('id', SocialContentMedia::query()->select('file_object_id')->whereNull('removed_at'));
            });
        }

        [$free, $diskTotal] = $this->diskSpace();
        $total = $video + $image + $other;
        $warnBytes = max(0, (int) config('konelsis.social_media.storage_warn_free_gb', 10)) * 1073741824;

        return $this->storageMemo = [
            'video_bytes' => $video,
            'image_bytes' => $image,
            'other_bytes' => $other,
            'total_bytes' => $total,
            'removed_bytes' => $removed,
            'free_bytes' => $free,
            'disk_total_bytes' => $diskTotal,
            'video_human' => $this->humanBytes($video),
            'image_human' => $this->humanBytes($image),
            'other_human' => $this->humanBytes($other),
            'total_human' => $this->humanBytes($total),
            'removed_human' => $this->humanBytes($removed),
            'free_human' => $free === null ? null : $this->humanBytes($free),
            'disk_total_human' => $diskTotal === null ? null : $this->humanBytes($diskTotal),
            'low' => $free !== null && $free < $warnBytes,
        ];
    }

    // ------------------------------------------------------------------
    // Ayar ve liste okumalari
    // ------------------------------------------------------------------

    /**
     * Kategoriler (sira, sonra ad). Ayarlar ekrani pasifleri de gorur.
     *
     * @return Collection<int, SocialCategory>
     */
    public function categories(bool $activeOnly = false): Collection
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            return new Collection;
        }

        return SocialCategory::query()
            ->when($activeOnly, fn (Builder $query) => $query->where('status', ActiveStatus::Active->value))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Ozel gunler (takvim sirasiyla). Hesap verilirse o hesaba ozel gunler +
     * butun hesaplara gecerli gunler doner.
     *
     * @return Collection<int, SocialSpecialDay>
     */
    public function specialDays(?int $profileId = null, bool $activeOnly = false): Collection
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            return new Collection;
        }

        return SocialSpecialDay::query()
            ->with('profile')
            ->when($profileId !== null, function (Builder $query) use ($profileId): void {
                $query->where(fn (Builder $inner) => $inner->whereNull('profile_id')->orWhere('profile_id', $profileId));
            })
            ->when($activeOnly, fn (Builder $query) => $query->where('status', ActiveStatus::Active->value))
            ->orderBy('month')
            ->orderBy('day')
            ->orderBy('name')
            ->get();
    }

    /**
     * Hesap turunde gorunen izlenen hesaplar (SPEC 3.11): kurumsal hesapta
     * rakip firmalar + resmi kurumlar, yonetici hesabinda rakip yoneticiler +
     * resmi kurumlar. Baglantilar yukludur.
     *
     * @return Collection<int, SocialWatchAccount>
     */
    public function watchAccounts(SocialProfileKind $kind, bool $includeInactive = false): Collection
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            return new Collection;
        }

        return SocialWatchAccount::query()
            ->with('links')
            ->whereIn('kind', SocialWatchKind::valuesForProfileKind($kind))
            ->when(! $includeInactive, fn (Builder $query) => $query->where('status', ActiveStatus::Active->value))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Istatistik girisleri (yeni donem ustte); giren kisi ve rapor dosyasi yuklu.
     *
     * @return Collection<int, SocialMetricEntry>
     */
    public function metricEntries(?int $profileId = null, ?SocialPlatform $platform = null, int $limit = 300): Collection
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            return new Collection;
        }

        return SocialMetricEntry::query()
            ->with(['createdBy.orgUnit', 'reportFile'])
            ->when($profileId !== null, fn (Builder $query) => $query->where('profile_id', $profileId))
            ->when($platform !== null, fn (Builder $query) => $query->where('platform', $platform->value))
            ->orderByDesc('period_end_on')
            ->orderByDesc('period_start_on')
            ->orderByDesc('id')
            ->limit(max(1, min(1000, $limit)))
            ->get();
    }

    /** Hesap kaydi (baglantilari ve sahibi yuklu); yoksa null. */
    public function findProfile(int $profileId): ?SocialProfile
    {
        if ($profileId <= 0 || ! SchemaReadiness::hasBatch('B31')) {
            return null;
        }

        return SocialProfile::query()->with(['links', 'owner.orgUnit'])->whereKey($profileId)->first();
    }

    // ------------------------------------------------------------------
    // Yardimcilar: tarih ve ozel gun
    // ------------------------------------------------------------------

    /** `YYYY-MM` yazimindan ayin ilk gunu; gecersizse icinde bulunulan ay. */
    private function monthStart(string $month): CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', trim($month), $parts) === 1 && (int) $parts[1] >= 1970) {
            return SocialClock::date(sprintf('%04d-%02d-01', (int) $parts[1], (int) $parts[2]));
        }

        return SocialClock::today()->startOfMonth();
    }

    /**
     * Hesaba gecerli AKTIF ozel gunlerin donem icindeki tarihleri (tarih sirali).
     *
     * @return list<array{date: string, day: SocialSpecialDay}>
     */
    private function occurrences(int $profileId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');
        $found = [];

        foreach ($this->specialDays($profileId, true) as $day) {
            for ($year = (int) $from->format('Y'); $year <= (int) $to->format('Y'); $year++) {
                $date = $day->dateInYear($year);

                if ($date !== null && $date >= $fromDate && $date <= $toDate) {
                    $found[] = ['date' => $date, 'day' => $day];
                }
            }
        }

        usort($found, fn (array $a, array $b): int => [$a['date'], (string) $a['day']->name] <=> [$b['date'], (string) $b['day']->name]);

        return $found;
    }

    private function hasActiveSpecialDays(int $profileId): bool
    {
        return SocialSpecialDay::query()
            ->where('status', ActiveStatus::Active->value)
            ->where(fn (Builder $query) => $query->whereNull('profile_id')->orWhere('profile_id', $profileId))
            ->exists();
    }

    /**
     * Donemdeki gunlerin icerik kapsami: `published` = o gune ait paylasilmis
     * icerik olan gunler (plan gunu ve paylasim gunu), `active` = o gune planli,
     * henuz paylasilmamis ama gecerli (arsiv / ret disi) icerik olan gunler.
     *
     * @return array{published: array<string, true>, active: array<string, true>}
     */
    private function coverage(int $profileId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$startUtc, $endUtc] = $this->utcBounds($from, $to);
        $timezone = SocialClock::timezone();
        $coverage = ['published' => [], 'active' => []];

        $rows = SocialContent::query()
            ->where('profile_id', $profileId)
            ->where(function (Builder $query) use ($from, $to, $startUtc, $endUtc): void {
                $query->whereBetween('planned_on', [$from->format('Y-m-d'), $to->format('Y-m-d')])
                    ->orWhere(fn (Builder $inner) => $inner->where('published_at', '>=', $startUtc)->where('published_at', '<', $endUtc));
            })
            ->get(['id', 'status', 'planned_on', 'published_at']);

        foreach ($rows as $row) {
            $plannedDay = $row->planned_on?->format('Y-m-d');

            if ($row->published_at !== null) {
                $coverage['published'][$this->localDay($row->published_at, $timezone)] = true;

                if ($plannedDay !== null) {
                    $coverage['published'][$plannedDay] = true;
                }

                continue;
            }

            if ($plannedDay !== null && ! in_array($row->status, [SocialContentStatus::Archived, SocialContentStatus::Rejected], true)) {
                $coverage['active'][$plannedDay] = true;
            }
        }

        return $coverage;
    }

    /**
     * @param  array{published: array<string, true>, active: array<string, true>}  $coverage
     */
    private function isCovered(string $date, array $coverage): bool
    {
        if (isset($coverage['published'][$date])) {
            return true;
        }

        return $date >= SocialClock::todayString() && isset($coverage['active'][$date]);
    }

    /**
     * Kurum saatindeki gun araliginin UTC sinirlari: [baslangic, bitis) .
     *
     * @return array{0: string, 1: string}
     */
    private function utcBounds(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            SocialClock::date($from)->utc()->format('Y-m-d H:i:s'),
            SocialClock::date($to)->addDay()->utc()->format('Y-m-d H:i:s'),
        ];
    }

    private function localDay(DateTimeInterface $instant, string $timezone): string
    {
        return CarbonImmutable::instance($instant)->setTimezone($timezone)->format('Y-m-d');
    }

    // ------------------------------------------------------------------
    // Yardimcilar: analiz
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function emptyAnalytics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'range' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'kpis' => [
                'published' => 0,
                'planned' => 0,
                'missed_planned' => 0,
                'special_days' => 0,
                'missed_special_days' => 0,
                'pending' => 0,
                'revision_requested' => 0,
                'approved_unpublished' => 0,
                'rejected' => 0,
                'avg_approval_hours' => null,
            ],
            'special_days_defined' => false,
            'by_platform' => [],
            'by_type' => [],
            'by_month' => $this->byMonth($from, $to, new Collection, []),
            'missed_special_list' => [],
            'missed_planned_list' => [],
            'top_creators' => [],
            'followers' => [],
            'metrics_summary' => [],
        ];
    }

    /**
     * Arsiv ve ret: plana, hatirlatmaya ve "atlandi" hesabina girmeyen durumlar.
     *
     * @return list<string>
     */
    private function deadStatuses(): array
    {
        return [SocialContentStatus::Archived->value, SocialContentStatus::Rejected->value];
    }

    /**
     * Donem icinde paylasilan icerikler (hafif satirlar).
     *
     * @return Collection<int, SocialContent>
     */
    private function publishedRows(int $profileId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        [$startUtc, $endUtc] = $this->utcBounds($from, $to);

        return SocialContent::query()
            ->where('profile_id', $profileId)
            ->where('published_at', '>=', $startUtc)
            ->where('published_at', '<', $endUtc)
            ->get(['id', 'content_type', 'published_at', 'created_by_personnel_id']);
    }

    private function missedPlannedQuery(int $profileId, string $fromDate, string $toDate, string $today): Builder
    {
        return SocialContent::query()
            ->where('profile_id', $profileId)
            ->whereBetween('planned_on', [$fromDate, $toDate])
            ->where('planned_on', '<', $today)
            ->whereNull('published_at')
            ->whereNotIn('status', $this->deadStatuses());
    }

    /**
     * Hesabin su anki durum sayilari.
     *
     * @return array{pending: int, revision_requested: int, approved_unpublished: int, rejected: int}
     */
    private function statusSnapshot(int $profileId): array
    {
        $rows = SocialContent::query()
            ->where('profile_id', $profileId)
            ->whereIn('status', [
                SocialContentStatus::Pending->value,
                SocialContentStatus::RevisionRequested->value,
                SocialContentStatus::Approved->value,
                SocialContentStatus::Rejected->value,
            ])
            ->selectRaw('status, count(*) as total, sum(case when published_at is null then 1 else 0 end) as unpublished')
            ->groupBy('status')
            ->toBase()
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->status);

        $total = fn (SocialContentStatus $status): int => (int) ($rows->get($status->value)->total ?? 0);

        return [
            'pending' => $total(SocialContentStatus::Pending),
            'revision_requested' => $total(SocialContentStatus::RevisionRequested),
            'approved_unpublished' => (int) ($rows->get(SocialContentStatus::Approved->value)->unpublished ?? 0),
            'rejected' => $total(SocialContentStatus::Rejected),
        ];
    }

    /** Donemde onaylanan iceriklerin ortalama onay suresi (saat); veri yoksa null. */
    private function averageApprovalHours(int $profileId, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        [$startUtc, $endUtc] = $this->utcBounds($from, $to);

        $rows = SocialContent::query()
            ->where('profile_id', $profileId)
            ->where('status', SocialContentStatus::Approved->value)
            ->where('decided_at', '>=', $startUtc)
            ->where('decided_at', '<', $endUtc)
            ->orderByDesc('decided_at')
            ->limit(5000)
            ->get(['id', 'created_at', 'decided_at']);

        $seconds = [];

        foreach ($rows as $row) {
            if ($row->created_at === null || $row->decided_at === null) {
                continue;
            }

            $seconds[] = max(0, $row->decided_at->getTimestamp() - $row->created_at->getTimestamp());
        }

        if ($seconds === []) {
            return null;
        }

        return round(array_sum($seconds) / count($seconds) / 3600, 1);
    }

    /**
     * Donemde paylasilan iceriklerin hedef platformlara dagilimi (yedi platform, sifirlar dahil).
     *
     * @return list<array{platform: string, platform_label: string, color: string, published: int}>
     */
    private function publishedByPlatform(int $profileId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$startUtc, $endUtc] = $this->utcBounds($from, $to);

        $totals = SocialContentPlatform::query()
            ->join('social_contents', 'social_contents.id', '=', 'social_content_platforms.content_id')
            ->where('social_contents.profile_id', $profileId)
            ->where('social_contents.published_at', '>=', $startUtc)
            ->where('social_contents.published_at', '<', $endUtc)
            ->selectRaw('social_content_platforms.platform as platform, count(*) as total')
            ->groupBy('social_content_platforms.platform')
            ->toBase()
            ->pluck('total', 'platform');

        $rows = [];

        foreach (SocialPlatform::cases() as $platform) {
            $rows[] = [
                'platform' => $platform->value,
                'platform_label' => (string) $platform->getLabel(),
                'color' => $platform->brandColor(),
                'published' => (int) ($totals[$platform->value] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, SocialContent>  $published
     * @return list<array{content_type: string, label: string, published: int}>
     */
    private function publishedByType(Collection $published): array
    {
        $totals = [];

        foreach ($published as $row) {
            $type = $row->content_type instanceof SocialContentType ? $row->content_type->value : (string) $row->content_type;
            $totals[$type] = ($totals[$type] ?? 0) + 1;
        }

        $rows = [];

        foreach (SocialContentType::cases() as $type) {
            $rows[] = [
                'content_type' => $type->value,
                'label' => (string) $type->getLabel(),
                'published' => (int) ($totals[$type->value] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Ay ay paylasilan ve atlanan planli icerik sayilari (donemin butun aylari).
     *
     * @param  Collection<int, SocialContent>  $published
     * @param  list<string>  $missedDates  Atlanan planli iceriklerin plan tarihleri (Y-m-d).
     * @return list<array{month: string, published: int, missed: int}>
     */
    private function byMonth(CarbonImmutable $from, CarbonImmutable $to, Collection $published, array $missedDates): array
    {
        $months = [];

        for ($cursor = $from->startOfMonth(); $cursor->lessThanOrEqualTo($to); $cursor = $cursor->addMonthNoOverflow()) {
            $months[$cursor->format('Y-m')] = ['month' => $cursor->format('Y-m'), 'published' => 0, 'missed' => 0];
        }

        $timezone = SocialClock::timezone();

        foreach ($published as $row) {
            if ($row->published_at === null) {
                continue;
            }

            $key = substr($this->localDay($row->published_at, $timezone), 0, 7);

            if (isset($months[$key])) {
                $months[$key]['published']++;
            }
        }

        foreach ($missedDates as $date) {
            $key = substr($date, 0, 7);

            if (isset($months[$key])) {
                $months[$key]['missed']++;
            }
        }

        return array_values($months);
    }

    /**
     * Donemde en cok icerigi paylasilan hazirlayanlar.
     *
     * @param  Collection<int, SocialContent>  $published
     * @return list<array{person: Personnel, count: int}>
     */
    private function topCreators(Collection $published): array
    {
        $totals = [];

        foreach ($published as $row) {
            $creatorId = $row->getAttribute('created_by_personnel_id');

            if ($creatorId !== null) {
                $totals[(int) $creatorId] = ($totals[(int) $creatorId] ?? 0) + 1;
            }
        }

        if ($totals === []) {
            return [];
        }

        arsort($totals);
        $totals = array_slice($totals, 0, self::TOP_CREATORS, true);

        $people = Personnel::query()
            ->with('orgUnit')
            ->whereIn('id', array_keys($totals))
            ->get()
            ->keyBy(fn (Personnel $personnel): int => (int) $personnel->getKey());

        $rows = [];

        foreach ($totals as $personnelId => $count) {
            $person = $people->get($personnelId);

            if ($person instanceof Personnel) {
                $rows[] = ['person' => $person, 'count' => (int) $count];
            }
        }

        return $rows;
    }

    /**
     * Donemle kesisen istatistik girisleri (eski donem once).
     *
     * @return Collection<int, SocialMetricEntry>
     */
    private function overlappingEntries(int $profileId, string $fromDate, string $toDate): Collection
    {
        return SocialMetricEntry::query()
            ->where('profile_id', $profileId)
            ->where('period_start_on', '<=', $toDate)
            ->where('period_end_on', '>=', $fromDate)
            ->orderBy('period_end_on')
            ->orderBy('period_start_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Donem baslamadan onceki son takipci sayilari (platform => deger); degisim
     * hesabinin tabani. Donem oncesi giris yoksa donemdeki ilk deger taban olur.
     *
     * @return array<string, int>
     */
    private function followerBaselines(int $profileId, string $fromDate): array
    {
        $rows = SocialMetricEntry::query()
            ->where('profile_id', $profileId)
            ->where('period_end_on', '<', $fromDate)
            ->whereNotNull('followers')
            ->orderByDesc('period_end_on')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'platform', 'followers', 'period_end_on']);

        $baselines = [];

        foreach ($rows as $row) {
            $platform = $row->platform instanceof SocialPlatform ? $row->platform->value : (string) $row->platform;

            if (! array_key_exists($platform, $baselines)) {
                $baselines[$platform] = (int) $row->followers;
            }
        }

        return $baselines;
    }

    /**
     * Platform basina takipci egrisi (donem bitis tarihi => takipci).
     *
     * @param  Collection<int, SocialMetricEntry>  $entries
     * @return list<array{platform: string, platform_label: string, color: string, points: list<array{date: string, value: int}>}>
     */
    private function followerSeries(Collection $entries): array
    {
        $points = [];

        foreach ($entries as $entry) {
            if ($entry->followers === null || $entry->period_end_on === null || ! $entry->platform instanceof SocialPlatform) {
                continue;
            }

            // Ayni bitis tarihli iki giriste sonuncusu gecerlidir.
            $points[$entry->platform->value][$entry->period_end_on->format('Y-m-d')] = (int) $entry->followers;
        }

        $series = [];

        foreach (SocialPlatform::cases() as $platform) {
            if (! isset($points[$platform->value])) {
                continue;
            }

            ksort($points[$platform->value]);
            $list = [];

            foreach ($points[$platform->value] as $date => $value) {
                $list[] = ['date' => (string) $date, 'value' => $value];
            }

            $series[] = [
                'platform' => $platform->value,
                'platform_label' => (string) $platform->getLabel(),
                'color' => $platform->brandColor(),
                'points' => $list,
            ];
        }

        return $series;
    }

    /**
     * Platform basina istatistik ozeti (F10): son takipci, takipci degisimi,
     * donemle kesisen girislerin toplamlari ve etkilesim orani (%).
     * Hic girilmemis olcum null kalir (0 "girildi ve sifir" demektir).
     *
     * @param  Collection<int, SocialMetricEntry>  $entries
     * @param  array<string, int>  $baselines
     * @return list<array<string, mixed>>
     */
    private function metricsSummary(Collection $entries, array $baselines): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            if ($entry->platform instanceof SocialPlatform) {
                $grouped[$entry->platform->value][] = $entry;
            }
        }

        $summary = [];

        foreach (SocialPlatform::cases() as $platform) {
            $rows = $grouped[$platform->value] ?? [];

            if ($rows === []) {
                continue;
            }

            $first = null;
            $latest = null;
            $sums = array_fill_keys(self::SUMMED_METRICS, null);

            foreach ($rows as $entry) {
                if ($entry->followers !== null) {
                    $first ??= (int) $entry->followers;
                    $latest = (int) $entry->followers;
                }

                foreach (self::SUMMED_METRICS as $metric) {
                    $value = $entry->getAttribute($metric);

                    if ($value !== null) {
                        $sums[$metric] = (int) ($sums[$metric] ?? 0) + (int) $value;
                    }
                }
            }

            $baseline = $baselines[$platform->value] ?? $first;
            $base = (int) ($sums['impressions'] ?? 0) > 0 ? (int) $sums['impressions'] : (int) ($sums['reach'] ?? 0);

            $summary[] = [
                'platform' => $platform->value,
                'platform_label' => (string) $platform->getLabel(),
                'color' => $platform->brandColor(),
                'followers_latest' => $latest,
                'followers_delta' => $latest !== null && $baseline !== null ? $latest - $baseline : null,
                'posts_count' => $sums['posts_count'],
                'impressions' => $sums['impressions'],
                'reach' => $sums['reach'],
                'engagements' => $sums['engagements'],
                'profile_visits' => $sums['profile_visits'],
                'link_clicks' => $sums['link_clicks'],
                'video_views' => $sums['video_views'],
                'engagement_rate' => $sums['engagements'] !== null && $base > 0
                    ? round(((int) $sums['engagements']) / $base * 100, 2)
                    : null,
                'entries' => count($rows),
            ];
        }

        return $summary;
    }

    // ------------------------------------------------------------------
    // Yardimcilar: depolama
    // ------------------------------------------------------------------

    /** Verilen turdeki BUTUN medya satirlarinin dosya kimlikleri (alt sorgu). */
    private function mediaFileIds(SocialMediaKind $kind): Builder
    {
        return SocialContentMedia::query()->select('file_object_id')->where('kind', $kind->value);
    }

    /** Kapak karesi / onizleme kolonundaki dosya kimlikleri (alt sorgu). */
    private function mediaColumnIds(string $column): Builder
    {
        return SocialContentMedia::query()->select($column)->whereNotNull($column);
    }

    /**
     * Kosula uyan AKTIF dosya nesnelerinin bayt toplami. Toplam `file_objects`
     * satirlari uzerinden alindigi icin her dosya bir kez sayilir.
     *
     * @param  callable(Builder): mixed  $scope
     */
    private function sumBytes(callable $scope): int
    {
        $query = FileObject::query()->where('status', FileObjectStatus::Active->value);
        $scope($query);

        return (int) $query->sum('byte_size');
    }

    /**
     * Depolama bolumundeki bos ve toplam alan; okunamazsa null.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function diskSpace(): array
    {
        $path = storage_path();

        if (! is_dir($path)) {
            return [null, null];
        }

        $free = function_exists('disk_free_space') ? @disk_free_space($path) : false;
        $total = function_exists('disk_total_space') ? @disk_total_space($path) : false;

        if ($free === false || $total === false || $total <= 0) {
            return [null, null];
        }

        return [(int) $free, (int) $total];
    }

    /** Okunur boyut (FileObject::humanSize ile ayni yazim, GB dahil). */
    private function humanBytes(int $bytes): string
    {
        $bytes = max(0, $bytes);

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1, ',', '.').' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.').' KB';
        }

        return $bytes.' B';
    }
}
