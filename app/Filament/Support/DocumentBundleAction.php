<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Platform\Feature;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Personnel\Personnel;
use App\Services\Document\DocumentBundleService;
use App\Services\Platform\FeatureFlags;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * "Tum belgeleri indir" dugmesi (D-176): kaydin belgelerini klasorlu tek ZIP
 * olarak indiren baglanti (DocumentBundleController). Ozellik kapaliysa,
 * kisi kaydi goremiyorsa ya da indirilebilecek belge yoksa gorunmez.
 *
 * Kullanildigi yerler: teklif sayfasinin Dokumanlar sekmesi, potansiyel is
 * sayfasinin Belgeler karti ve (D-184) teklif / potansiyel is goruntule ve
 * duzenle sayfalarinin basligi (yalniz simge). Proje Dokumanlar sekmesi, sozlesme surumleri ve
 * ihale belgeleri ayni dugmeyi DocumentBundleQueries'e kendi satir yontemi
 * eklenerek kullanabilir.
 */
final class DocumentBundleAction
{
    /** @var array<string, bool> Ayni istekte gorunurluk bir kez hesaplanir. */
    private static array $available = [];

    public static function proposal(Proposal $proposal): Action
    {
        return self::make(
            'proposal',
            ['proposal' => $proposal->getKey()],
            'p'.$proposal->getKey(),
            static fn (Personnel $viewer): bool => Gate::forUser($viewer)->allows('view', $proposal)
                && app(DocumentBundleService::class)->proposalHasDocuments($proposal, $viewer),
        );
    }

    public static function businessCase(BusinessCase $case): Action
    {
        return self::make('business-case', ['businessCase' => $case->getKey()], 'c'.$case->getKey(), self::businessCaseCheck($case));
    }

    /**
     * D-184 (9 Ekim 2026 kullanici: "Tum belgeleri indir kismi teklif duzenle ve
     * teklif goruntule action kisminda olmalidir"): sayfa basligindaki dugme.
     * D-181 kisa baslik duzeni: yalniz simge, adi ipucunda. Ayni sayfadaki kart
     * dugmesiyle ad cakismasin diye ayri ad.
     */
    public static function proposalHeader(Proposal $proposal): Action
    {
        return self::header(self::proposal($proposal));
    }

    /** Potansiyel is goruntule / duzenle basligi (D-184, teklifle ayni duzen). */
    public static function businessCaseHeader(BusinessCase $case): Action
    {
        return self::header(self::businessCase($case));
    }

    private static function header(Action $action): Action
    {
        return $action
            ->name('download_all_documents_header')
            ->tooltip(__('document_bundle.actions.download_all'))
            ->iconButton();
    }

    /** Potansiyel iste dugme gorunecek mi (kartin cizilip cizilmeyecegi icin). */
    public static function businessCaseAvailable(BusinessCase $case): bool
    {
        return self::available('c'.$case->getKey(), self::businessCaseCheck($case));
    }

    /**
     * @return callable(Personnel): bool
     */
    private static function businessCaseCheck(BusinessCase $case): callable
    {
        return static fn (Personnel $viewer): bool => Gate::forUser($viewer)->allows('view', $case)
            && app(DocumentBundleService::class)->businessCaseHasDocuments($case, $viewer);
    }

    /**
     * @param  callable(Personnel): bool  $check
     */
    private static function available(string $cacheKey, callable $check): bool
    {
        if (! FeatureFlags::enabled(Feature::DocumentBundles)) {
            return false;
        }

        $viewer = Auth::user();

        if (! $viewer instanceof Personnel) {
            return false;
        }

        return self::$available[$cacheKey.':'.$viewer->getKey()] ??= $check($viewer);
    }

    /**
     * @param  array<string, int|string>  $parameters
     * @param  callable(Personnel): bool  $available
     */
    private static function make(string $route, array $parameters, string $cacheKey, callable $available): Action
    {
        return Action::make('download_all_documents')
            ->label(__('document_bundle.actions.download_all'))
            ->tooltip(__('document_bundle.actions.download_all_help'))
            ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
            ->color(ActionColors::NEUTRAL)
            ->url(static fn (): string => route(Filament::getCurrentOrDefaultPanel()->generateRouteName('files.bundle.'.$route), $parameters))
            ->visible(static fn (): bool => self::available($cacheKey, $available));
    }
}
