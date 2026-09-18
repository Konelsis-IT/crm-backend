<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\Shared\ActiveStatus;
use App\Enums\SocialMedia\SocialMetricSource;
use App\Enums\SocialMedia\SocialPlatform;
use App\Enums\SocialMedia\SocialWatchKind;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialCategory;
use App\Models\SocialMedia\SocialMetricEntry;
use App\Models\SocialMedia\SocialProfile;
use App\Models\SocialMedia\SocialProfileLink;
use App\Models\SocialMedia\SocialSpecialDay;
use App\Models\SocialMedia\SocialWatchAccount;
use App\Models\SocialMedia\SocialWatchLink;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Ayar, ilham ve istatistik verisini tarayiciya giden JSON bicimine cevirir
 * (salt okuma; SPEC 8): kategoriler, ozel gunler, izlenen hesap gruplari,
 * hesap (profil), sorumlu gorevler ve istatistik girisleri.
 *
 * Kimlik degerleri yalniz istemcinin kayit secmesi icin tasinir; ekranda
 * gosterilmez. Her enum deger + `_label` olarak gider. Platform rengi (hex)
 * yalniz grafik serileri icindir; dugmeler CSS degiskeninden beslenir (H5).
 * Dosya adresleri kok-goreli'dir (E17).
 *
 * Kisi bicimi SocialContentPresenter::person() ile ortaktir; iki sunum sinifi
 * birbirini kurucuda istemesin diye gec cozulur.
 */
final class SocialSettingsPresenter
{
    private const ROUTE_PREFIX = 'filament.admin.social.';

    // ------------------------------------------------------------------
    // Kategoriler
    // ------------------------------------------------------------------

    /**
     * @param  iterable<SocialCategory>  $categories
     * @return list<array<string, mixed>>
     */
    public function categories(iterable $categories): array
    {
        $rows = [];

        foreach ($categories as $category) {
            $rows[] = $this->category($category);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function category(SocialCategory $category): array
    {
        $color = (string) $category->color;

        return [
            'id' => (int) $category->getKey(),
            'name' => (string) $category->name,
            'color' => in_array($color, SocialCategory::COLORS, true) ? $color : SocialCategory::DEFAULT_COLOR,
            'sort_order' => (int) $category->sort_order,
            ...$this->status($category->status),
        ];
    }

    // ------------------------------------------------------------------
    // Ozel gunler
    // ------------------------------------------------------------------

    /**
     * @param  iterable<SocialSpecialDay>  $days
     * @return list<array<string, mixed>>
     */
    public function days(iterable $days): array
    {
        $rows = [];

        foreach ($days as $day) {
            $rows[] = $this->day($day);
        }

        return $rows;
    }

    /**
     * `date_label` okunur tarih ("29 Ekim" / "29 Ekim 2026"), `next_on` gunun
     * bugun ya da sonrasindaki ilk tarihidir (gecmiste kalmis tek yillik gunde null).
     *
     * @return array<string, mixed>
     */
    public function day(SocialSpecialDay $day): array
    {
        return [
            'id' => (int) $day->getKey(),
            'name' => (string) $day->name,
            'month' => (int) $day->month,
            'day' => (int) $day->day,
            'year' => $day->year === null ? null : (int) $day->year,
            'profile_id' => $day->profile_id === null ? null : (int) $day->profile_id,
            'profile_name' => $day->profile_id === null ? null : $day->profile?->name,
            'note' => $day->note,
            'recurring' => $day->isRecurring(),
            'date_label' => $this->dayLabel($day),
            'next_on' => $this->nextOccurrence($day),
            ...$this->status($day->status),
        ];
    }

    // ------------------------------------------------------------------
    // Izlenen hesaplar (Ilham ve Rakipler)
    // ------------------------------------------------------------------

    /**
     * Hesap turunde gorunen izleme turleri sirasiyla gruplar; bos grup da
     * doner (arayuz bos durum + "ekle" gosterebilsin).
     *
     * @param  iterable<SocialWatchAccount>  $accounts
     * @return list<array{kind: string, kind_label: string, accounts: list<array<string, mixed>>}>
     */
    public function watchGroups(SocialProfile $profile, iterable $accounts): array
    {
        $groups = [];

        foreach (SocialWatchKind::forProfileKind($profile->kind) as $kind) {
            $groups[$kind->value] = [
                'kind' => $kind->value,
                'kind_label' => (string) $kind->getLabel(),
                'accounts' => [],
            ];
        }

        foreach ($accounts as $account) {
            $kind = $account->kind instanceof SocialWatchKind ? $account->kind->value : (string) $account->kind;

            if (isset($groups[$kind])) {
                $groups[$kind]['accounts'][] = $this->watchAccount($account);
            }
        }

        return array_values($groups);
    }

    /**
     * @return array<string, mixed>
     */
    public function watchAccount(SocialWatchAccount $account): array
    {
        $kind = $account->kind;

        return [
            'id' => (int) $account->getKey(),
            'kind' => $kind instanceof SocialWatchKind ? $kind->value : (string) $kind,
            'kind_label' => $kind instanceof SocialWatchKind ? (string) $kind->getLabel() : null,
            'name' => (string) $account->name,
            'initials' => $this->initials((string) $account->name),
            'subtitle' => $account->subtitle,
            'note' => $account->note,
            'sort_order' => (int) $account->sort_order,
            ...$this->status($account->status),
            'links' => $this->links($account->links),
        ];
    }

    // ------------------------------------------------------------------
    // Hesap (profil)
    // ------------------------------------------------------------------

    /**
     * bootstrap.profiles ile ayni bicim.
     *
     * @return array<string, mixed>
     */
    public function profile(SocialProfile $profile): array
    {
        return [
            'id' => (int) $profile->getKey(),
            'code' => (string) $profile->code,
            'name' => (string) $profile->name,
            'initials' => $this->initials((string) $profile->name),
            'kind' => $profile->kind->value,
            'kind_label' => (string) $profile->kind->getLabel(),
            'bio' => $profile->bio,
            'owner' => $this->person($profile->owner),
            'links' => $this->links($profile->links),
        ];
    }

    // ------------------------------------------------------------------
    // Sorumlu gorevler
    // ------------------------------------------------------------------

    /**
     * @param  list<array{id: int, label: string, selected: bool}>  $positions
     * @param  iterable<Personnel>  $people
     * @return array{positions: list<array{id: int, label: string, selected: bool}>, people: list<array<string, mixed>>}
     */
    public function responsibles(array $positions, iterable $people): array
    {
        $rows = [];

        foreach ($people as $personnel) {
            $person = $this->person($personnel);

            if ($person !== null) {
                $rows[] = $person;
            }
        }

        return [
            'positions' => array_values(array_map(fn (array $position): array => [
                'id' => (int) $position['id'],
                'label' => (string) $position['label'],
                'selected' => (bool) $position['selected'],
            ], $positions)),
            'people' => $rows,
        ];
    }

    // ------------------------------------------------------------------
    // Istatistik girisleri
    // ------------------------------------------------------------------

    /**
     * @param  iterable<SocialMetricEntry>  $entries
     * @return list<array<string, mixed>>
     */
    public function metricEntries(iterable $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = $this->metricEntry($entry);
        }

        return $rows;
    }

    /**
     * `report_url` satir ici acilir (PDF / gorsel), `report_download_url` indirir;
     * tablo dosyalari (csv, xls, xlsx) sunucuda her zaman indirme olarak verilir.
     *
     * @return array<string, mixed>
     */
    public function metricEntry(SocialMetricEntry $entry): array
    {
        $platform = $entry->platform;
        $source = $entry->source;
        $file = $entry->hasReport() ? $entry->reportFile : null;
        $url = $file === null ? null : $this->url('metrics.file', ['entry' => $entry->getKey()]);

        $row = [
            'id' => (int) $entry->getKey(),
            'profile_id' => (int) $entry->profile_id,
            'platform' => $platform instanceof SocialPlatform ? $platform->value : (string) $platform,
            'platform_label' => $platform instanceof SocialPlatform ? (string) $platform->getLabel() : null,
            'color' => $platform instanceof SocialPlatform ? $platform->brandColor() : null,
            'period_start_on' => $entry->period_start_on?->format('Y-m-d'),
            'period_end_on' => $entry->period_end_on?->format('Y-m-d'),
        ];

        foreach (SocialMetricEntry::METRICS as $metric) {
            $value = $entry->getAttribute($metric);
            $row[$metric] = $value === null ? null : (int) $value;
        }

        return $row + [
            'note' => $entry->note,
            'source' => $source instanceof SocialMetricSource ? $source->value : (string) $source,
            'source_label' => $source instanceof SocialMetricSource ? (string) $source->getLabel() : null,
            'report_url' => $url,
            'report_download_url' => $url === null ? null : $url.'?disposition=download',
            'report_name' => $file?->original_name,
            'report_size' => $file?->humanSize(),
            'report_previewable' => $file !== null && $file->isInlinePreviewable()
                && ($file->isImage() || (string) $file->mime_type === 'application/pdf'),
            'author' => $this->person($entry->createdBy),
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }

    // ------------------------------------------------------------------
    // Ortak parcalar
    // ------------------------------------------------------------------

    /**
     * Platform baglantilari (platform sirasiyla). Kendi hesabimizin
     * baglantisinda `handle` de gider.
     *
     * @param  iterable<SocialProfileLink|SocialWatchLink>|null  $links
     * @return list<array<string, mixed>>
     */
    public function links(?iterable $links): array
    {
        $byPlatform = [];

        foreach ($links ?? [] as $link) {
            $platform = $link->platform instanceof SocialPlatform ? $link->platform : SocialPlatform::tryFrom((string) $link->platform);

            if ($platform !== null && filled($link->url)) {
                $byPlatform[$platform->value] = [$platform, $link];
            }
        }

        $rows = [];

        foreach (SocialPlatform::cases() as $case) {
            if (! isset($byPlatform[$case->value])) {
                continue;
            }

            [$platform, $link] = $byPlatform[$case->value];

            $row = [
                'platform' => $platform->value,
                'platform_label' => (string) $platform->getLabel(),
                'url' => (string) $link->url,
                'color' => $platform->brandColor(),
            ];

            if ($link instanceof SocialProfileLink) {
                $row['handle'] = $link->handle;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Kisi bicimi (SPEC 8 `person`); kayit yoksa null.
     *
     * @return array<string, mixed>|null
     */
    public function person(?Personnel $personnel): ?array
    {
        if ($personnel === null) {
            return null;
        }

        return app(SocialContentPresenter::class)->person($personnel);
    }

    /**
     * @return array{status: string, status_label: string}
     */
    private function status(mixed $status): array
    {
        $status = $status instanceof ActiveStatus ? $status : (ActiveStatus::tryFrom((string) $status) ?? ActiveStatus::Active);

        return ['status' => $status->value, 'status_label' => (string) $status->getLabel()];
    }

    private function dayLabel(SocialSpecialDay $day): string
    {
        $year = $day->year === null ? 2024 : (int) $day->year;

        if (! checkdate((int) $day->month, (int) $day->day, $year)) {
            return sprintf('%02d.%02d', (int) $day->day, (int) $day->month).($day->year === null ? '' : '.'.$year);
        }

        $date = CarbonImmutable::create($year, (int) $day->month, (int) $day->day, 0, 0, 0, SocialClock::timezone())
            ->locale((string) app()->getLocale());

        return $date->translatedFormat($day->year === null ? 'j F' : 'j F Y');
    }

    private function nextOccurrence(SocialSpecialDay $day): ?string
    {
        $today = SocialClock::todayString();

        if ($day->year !== null) {
            $date = $day->dateInYear((int) $day->year);

            return $date !== null && $date >= $today ? $date : null;
        }

        $year = (int) substr($today, 0, 4);

        // 29 Subat gibi her yil bulunmayan gunler icin birkac yil ileri bakilir.
        for ($offset = 0; $offset <= 8; $offset++) {
            $date = $day->dateInYear($year + $offset);

            if ($date !== null && $date >= $today) {
                return $date;
            }
        }

        return null;
    }

    /** Ad bas harfleri (Turkce buyuk harf kuraliyla: i -> I noktali). */
    private function initials(string $name): string
    {
        $words = array_values(array_filter(preg_split('/\s+/u', trim($name)) ?: []));
        $letters = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $first = mb_substr($word, 0, 1);
            $letters .= match ($first) {
                'i' => "\u{0130}",
                "\u{0131}" => 'I',
                default => mb_strtoupper($first),
            };
        }

        return $letters === '' ? '?' : $letters;
    }

    /**
     * Kok-goreli modul adresi; rota henuz tanimli degilse null.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function url(string $name, array $parameters = []): ?string
    {
        try {
            return route(self::ROUTE_PREFIX.$name, $parameters, false);
        } catch (Throwable) {
            return null;
        }
    }
}
