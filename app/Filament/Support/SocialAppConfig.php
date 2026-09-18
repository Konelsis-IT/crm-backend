<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Resources\SocialContents\SocialContentResource;
use App\Services\SocialMedia\SocialClock;
use Throwable;

/**
 * Sosyal Medya React uygulamasinin (D-106) sayfa ile birlikte tasinan
 * yapilandirmasi: `data-config` ozniteligine JSON olarak yazilir
 * (resources/views/filament/social/app.blade.php).
 *
 * - `endpoints`: filament.admin.social.* rotalarinin TAMAMI; anahtar = rota
 *   adinin `social.` sonrasi kismi (or. `contents.show`, `media.file`). Kayit
 *   kimligi `__ID__`, yukleme jetonu `__TOKEN__` yer tutucusuyla uretilir;
 *   istemci KS.url(ad, {id, token}) ile doldurur. Adresler kok-goreli'dir.
 * - Dinamik sunucu verisi (kisi, hesaplar, sayaclar, sinirlar) burada degil,
 *   `bootstrap` ucundadir.
 * - Derin baglanti parametreleri Turkcedir: icerik, gorunum, hesap.
 *
 * Sorgu zinciri yoktur; yalniz rota, dil ve yapilandirma okur.
 */
final class SocialAppConfig
{
    /** Detayi acilacak icerik (bildirim ve pano baglantilari). */
    public const PARAM_CONTENT = 'icerik';

    /** Acilacak gorunum. */
    public const PARAM_VIEW = 'gorunum';

    /** Secili hesap (profil kodu). */
    public const PARAM_PROFILE = 'hesap';

    public const VIEW_FEED = 'akis';

    public const VIEW_PLAN = 'plan';

    public const VIEW_INSIGHTS = 'ilham';

    public const VIEW_ANALYTICS = 'analiz';

    public const VIEW_SETTINGS = 'ayarlar';

    /** @var list<string> */
    public const VIEWS = [
        self::VIEW_FEED,
        self::VIEW_PLAN,
        self::VIEW_INSIGHTS,
        self::VIEW_ANALYTICS,
        self::VIEW_SETTINGS,
    ];

    private const ROUTE_PREFIX = 'filament.admin.social.';

    private const ID = '__ID__';

    private const TOKEN = '__TOKEN__';

    /**
     * Rota adi (social. sonrasi) => yer tutuculu parametreler. AdminPanelProvider'daki
     * rota grubu ile bire bir ayni liste; yeni rota eklenince buraya da eklenir.
     *
     * @var array<string, array<string, string>>
     */
    private const ROUTES = [
        // Icerik
        'bootstrap' => [],
        'counts' => [],
        'contents' => [],
        'contents.store' => [],
        'contents.show' => ['content' => self::ID],
        'contents.update' => ['content' => self::ID],
        'contents.status' => ['content' => self::ID],
        'contents.publish' => ['content' => self::ID],
        'contents.unpublish' => ['content' => self::ID],
        'contents.urgent' => ['content' => self::ID],
        'contents.reaction' => ['content' => self::ID],
        // Yorumlar
        'comments.store' => ['content' => self::ID],
        'comments.resolve' => ['comment' => self::ID],
        // Medya
        'media.store' => ['content' => self::ID],
        'media.order' => ['content' => self::ID],
        'media.update' => ['media' => self::ID],
        'media.variant' => ['media' => self::ID],
        'media.select' => ['media' => self::ID],
        'media.remove' => ['media' => self::ID],
        'media.restore' => ['media' => self::ID],
        'media.poster' => ['media' => self::ID],
        'media.file' => ['media' => self::ID],
        'media.zip' => ['content' => self::ID],
        // Parcali video yukleme
        'uploads.begin' => [],
        'uploads.status' => ['token' => self::TOKEN],
        'uploads.chunk' => ['token' => self::TOKEN],
        'uploads.complete' => ['token' => self::TOKEN],
        'uploads.abort' => ['token' => self::TOKEN],
        // Plan, analiz, depolama
        'calendar' => [],
        'agenda' => [],
        'analytics' => [],
        'storage' => [],
        // Ilham ve rakipler, ayarlar
        'watch' => [],
        'watch.store' => [],
        'watch.update' => ['account' => self::ID],
        'categories.store' => [],
        'categories.update' => ['category' => self::ID],
        'days' => [],
        'days.store' => [],
        'days.update' => ['day' => self::ID],
        'profiles.update' => ['profile' => self::ID],
        'responsibles' => [],
        'responsibles.sync' => [],
        // Istatistik girisleri
        'metrics' => [],
        'metrics.store' => [],
        'metrics.update' => ['entry' => self::ID],
        'metrics.file' => ['entry' => self::ID],
        // Katalog
        'catalog' => [],
        'catalog.file' => [],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        $labels = __('social_content.ui');

        return [
            'endpoints' => self::endpoints(),
            'placeholders' => ['id' => self::ID, 'token' => self::TOKEN],
            'csrf' => csrf_token(),
            'locale' => app()->getLocale(),
            // Dil dosyasi cozulemezse __() metin doner; istemci bos sozlukle calisir.
            'labels' => is_array($labels) ? $labels : [],
            'logo' => asset('images/konelsis-favicon.png'),
            'page_url' => self::pageUrl(),
            'params' => [
                'content' => self::PARAM_CONTENT,
                'view' => self::PARAM_VIEW,
                'profile' => self::PARAM_PROFILE,
            ],
            'views' => [
                'feed' => self::VIEW_FEED,
                'plan' => self::VIEW_PLAN,
                'insights' => self::VIEW_INSIGHTS,
                'analytics' => self::VIEW_ANALYTICS,
                'settings' => self::VIEW_SETTINGS,
            ],
            // "Bugun" kurum saatine goredir (SocialClock); istemci takvimde bunu esas alir.
            'timezone' => SocialClock::timezone(),
            'today' => SocialClock::todayString(),
        ];
    }

    /**
     * Rota adi => kok-goreli adres. Tanimsiz bir rota (kismi kurulum) null
     * doner; istemci o ozelligi gizler, sayfa yine acilir.
     *
     * @return array<string, string|null>
     */
    public static function endpoints(): array
    {
        $endpoints = [];

        foreach (self::ROUTES as $name => $parameters) {
            try {
                $endpoints[$name] = route(self::ROUTE_PREFIX.$name, $parameters, absolute: false);
            } catch (Throwable) {
                $endpoints[$name] = null;
            }
        }

        return $endpoints;
    }

    /**
     * Rota adlari (social. sonrasi); rota grubu ile eslesme denetimi icin.
     *
     * @return list<string>
     */
    public static function routeNames(): array
    {
        return array_keys(self::ROUTES);
    }

    private static function pageUrl(): ?string
    {
        try {
            return SocialContentResource::getUrl('index', isAbsolute: false);
        } catch (Throwable) {
            return null;
        }
    }
}
