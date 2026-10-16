<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use App\Enums\Acquisition\OfferStatus;
use App\Enums\Acquisition\ProposalVersionStatus;
use App\Enums\Acquisition\SubmissionChannel;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersion;
use App\Services\Acquisition\AcquisitionIntakeService;
use App\Services\Acquisition\ProposalService;
use App\Services\Acquisition\ProposalVersionService;
use App\Support\DisplayTime;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * KKS teklif takip listesi guncellemesinde revizyon = yeni teklif surumu
 * (D-198, kullanici 13 Ekim 2026: "KKS surumu icin evet yap"; D-195: "Her
 * revizyonda yeni surum gelsin, oncekinin durumu da kaybedilmesin").
 *
 * Revizyon sayilan degisiklik (teklifin icerigi):
 * - "VERILEN TEKLIFLER" sayfasinda teklif tarihi ileri gitti (teklif yeniden
 *   gonderildi; onceki tarih gecersizse, or. 31.09, duzeltmedir, revizyon
 *   degildir);
 * - proje gucu (MWp) ya da depolama (MWh) degisti.
 * Proje adi, proje durumu ve notlar aciklamadir: surum ozetine tarihli satir
 * olarak eklenir (yeni surum acilmaz). Verilecek sayfasindaki tarih planlanan
 * tarihtir, revizyon degildir.
 *
 * Yeni surum ekrandaki "Yeni teklif surumu" ile ayni yoldan acilir
 * (AcquisitionIntakeService::newProposalVersion: kapsamlar, kapsam listeleri,
 * maliyet listeleri ve belgeler tasinir); tutar, gecerlilik ve ozet onceki
 * surumden gelir, ozete liste satiri eklenir. Listede Verilen ise yeni surum
 * listedeki tarihle Gonderildi'ye yurur ve Verilen olur; onceki surum kendi
 * durumunu korur (B55). Onaylandi / Kacan firsat teklif kapanmistir, yeni
 * surum acilmaz.
 */
final class KksRevisions
{
    public const SUBMITTED_SHEET = 'VERİLEN TEKLİFLER';

    /**
     * @param  array<string, mixed>  $row  Yeni listedeki satir.
     * @param  array<string, mixed>|null  $previous  Onceki listedeki ayni satir.
     */
    public static function isRevision(array $row, ?array $previous): bool
    {
        if ($previous === null) {
            return false;
        }

        if (self::numberChanged($previous['power_mwp'] ?? null, $row['power_mwp'] ?? null)
            || self::numberChanged($previous['storage_mwh'] ?? null, $row['storage_mwh'] ?? null)) {
            return true;
        }

        if ((string) ($row['sheet'] ?? '') !== self::SUBMITTED_SHEET) {
            return false;
        }

        $before = self::date($previous['offer_date'] ?? null);
        $after = self::date($row['offer_date'] ?? null);

        return $before !== null && $after !== null && $after->greaterThan($before);
    }

    /** Yeni surumun ozetine eklenen satir. */
    public static function summaryLine(array $row, ?array $previous, string $listDate): string
    {
        $parts = [];
        $date = static fn (?array $item): string => trim((string) ($item['offer_date'] ?? '') !== '' ? (string) $item['offer_date'] : (string) ($item['offer_date_text'] ?? ''));

        foreach ([
            'Teklif tarihi' => [$date($previous), $date($row)],
            'Proje gücü' => [trim((string) ($previous['power_mwp'] ?? '')), trim((string) ($row['power_mwp'] ?? ''))],
            'Depolama gücü' => [trim((string) ($previous['storage_mwh'] ?? '')), trim((string) ($row['storage_mwh'] ?? ''))],
        ] as $label => [$before, $after]) {
            if ($after !== '' && $after !== $before) {
                $parts[] = $label.': '.$after.($before !== '' ? ' (önceki: '.$before.')' : '');
            }
        }

        return $listDate.' listesi ('.$row['sheet'].', satır '.$row['excel_row'].'): yeni teklif sürümü'.($parts !== [] ? ' — '.implode('; ', $parts) : '').'.';
    }

    /**
     * Revizyon surumu acar; acilmadiysa (kapali teklif, zaten acilmis) null.
     *
     * @param  array<string, mixed>  $row
     */
    public function open(Proposal $proposal, array $row, string $summaryLine): ?ProposalVersion
    {
        $proposal->refresh();

        if (in_array($proposal->currentOfferStatus(), [OfferStatus::Approved, OfferStatus::Lost], true)) {
            return null;
        }

        /** @var ProposalVersion|null $current */
        $current = $proposal->currentVersion;

        if (! $current instanceof ProposalVersion || str_contains((string) $current->summary, $summaryLine)) {
            return null;
        }

        $submittedAt = (string) ($row['sheet'] ?? '') === self::SUBMITTED_SHEET ? self::date($row['offer_date'] ?? null)?->setTime(12, 0) : null;

        // Ayni tarihle zaten gonderilmis guncel surum: revizyon daha once acilmis.
        if ($submittedAt !== null && $current->submitted_at?->copy()->timezone(DisplayTime::zone())->toDateString() === $submittedAt->toDateString()) {
            return null;
        }

        $version = app(AcquisitionIntakeService::class)->newProposalVersion($proposal, [
            'total_price' => $current->total_price,
            'margin_pct' => $current->margin_pct,
            'validity_until' => $current->validity_until?->toDateString(),
            'is_critical_route' => (bool) $current->is_critical_route,
            'summary' => trim(trim((string) $current->summary)."\n".$summaryLine),
        ]);

        if ($submittedAt !== null) {
            $this->submit($proposal, $submittedAt);
        }

        return $version->refresh();
    }

    /** Yeni surum Taslak -> Incelemede -> Onaylandi -> Gonderildi (listedeki tarih); teklif Verilen. */
    private function submit(Proposal $proposal, Carbon $submittedAt): void
    {
        $versions = app(ProposalVersionService::class);

        foreach ([ProposalVersionStatus::Review, ProposalVersionStatus::Approved, ProposalVersionStatus::Submitted] as $target) {
            $version = $proposal->refresh()->currentVersion;

            if ($version === null || ! $version->status->canTransitionTo($target)) {
                continue;
            }

            $versions->changeStatus(
                (int) $version->getKey(),
                $target,
                $target === ProposalVersionStatus::Submitted ? SubmissionChannel::Email : null,
                null,
                $target === ProposalVersionStatus::Submitted ? $submittedAt : null,
            );
        }

        app(ProposalService::class)->syncOfferStatus([$proposal->refresh()], OfferStatus::Submitted, [OfferStatus::ToBeSubmitted]);
    }

    private static function date(mixed $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '' || preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $match) !== 1 || ! checkdate((int) $match[2], (int) $match[1], (int) $match[3])) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d.m.Y H:i', sprintf('%02d.%02d.%04d 00:00', $match[1], $match[2], $match[3]), DisplayTime::zone());
        } catch (Throwable) {
            return null;
        }
    }

    private static function numberChanged(mixed $before, mixed $after): bool
    {
        $before = trim((string) $before);
        $after = trim((string) $after);

        if ($before === '' || $after === '') {
            return false;
        }

        $toNumber = static fn (string $value): ?float => is_numeric($normal = str_replace(',', '.', $value)) ? (float) $normal : null;
        [$a, $b] = [$toNumber($before), $toNumber($after)];

        return $a !== null && $b !== null ? abs($a - $b) > 0.0001 : $before !== $after;
    }
}
