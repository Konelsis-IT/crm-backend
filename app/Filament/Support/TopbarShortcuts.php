<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Services\Platform\FeatureFlags;
use App\Enums\Platform\Feature;
use App\Filament\Pages\QuickActionSettings;
use App\Filament\Pages\Work\ControlMatrix;
use App\Filament\Pages\Work\WorkBoard;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Notifications\NotificationResource;
use App\Filament\Support\QuickActions\QuickActionCatalog;
use App\Models\Personnel\Personnel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Ust cubuk kisayollari (24 Eylul 2026 kullanici istegi), soldan saga:
 * zilin hemen yaninda "Tum bildirimleri gor", sonra "Hizli islemler",
 * "Is panosu", "Kontrol matrisi"; ardindan TR / EN. Is panosu, Kontrol
 * matrisi ve Tum bildirimler sol menude yoktur; buradan acilir.
 *
 * Bildirim, Is panosu ve Kontrol matrisi dugmeleri Filament'in kendi baglanti
 * eylemleridir. Hizli islemler dairesi kullanici onayli tek Blade
 * gorunumudur (filament.components.quick-actions + konelsis.css `.kc-qa*`).
 * Her dugme yalniz sayfayi acabilen kisiye gorunur; bulunulan sayfanin
 * dugmesi vurgulanir.
 *
 * Dar ekranda uc nokta menusu (D-224, 19 Ekim 2026 kullanici: "ekran boyutuna
 * gore o 3 noktadaki menuler dolacak"): ekran daraldikca kisayollar sirayla
 * ust cubuktan "Diger" menusune gecer - once TR / EN ve Kontrol matrisi, sonra
 * Tum bildirimler ve Is panosu, en son Hizli islemler. Menu
 * Filament'in kendi eylem grubudur; hangi genislikte neyin nerede durdugu
 * yalniz konelsis.css'tedir (`kc-tb-p1..p4` ust cubukta, `kc-tb-more-p1..p4`
 * menude; 1 en son tasinan). Her sey sigdiginda uc nokta gorunmez.
 *
 * Sablonlar (D-226): Is panosunun solunda acilir liste - kisinin departmaninin
 * ve herkesin ortak sablonlari, ustte "+ Sablon ekle", altta "Tum sablonlar"
 * (TemplateMenu). Panodaki sablon alaninin yerine gecer. Satirlarda Goruntule ve
 * Indir dugmeleri oldugu icin kullanici onayli Blade gorunumudur
 * (filament.components.template-menu); uc nokta menusune gecmez, dar ekranda
 * simge olarak ust cubukta kalir. Is panosu ve Sablonlar etiketi xl'den itibaren.
 */
final class TopbarShortcuts
{
    public static function render(): Htmlable
    {
        $user = auth()->user();

        if (! $user instanceof Personnel) {
            return new HtmlString('');
        }

        $parts = [
            fn (): string => NotificationResource::canAccess()
                ? self::iconButton('allNotifications', __('notification_inbox.open_all'), Heroicon::OutlinedQueueList, NotificationResource::getUrl(), NotificationResource::getRouteBaseName().'.*')
                    ->extraAttributes(['class' => 'kc-tb-p2'])
                    ->toHtml()
                : '',
            fn (): string => QuickActionSettings::canAccess() ? self::quickActions((int) $user->getKey()) : '',
            fn (): string => ($templates = self::templateMenu($user)) !== null
                ? view('filament.components.template-menu', ['menu' => $templates])->render()
                : '',
            // Simgeler sayfanin kendi simgesidir (24 Eylul 2026: daha anlasilir
            // simgeler - Is panosu is listesi panosu, Kontrol matrisi kontrol listesi).
            fn (): string => WorkBoard::canAccess()
                ? self::pageButton('workBoard', WorkBoard::getNavigationLabel(), WorkBoard::getNavigationIcon(), WorkBoard::getUrl(), WorkBoard::getRouteName())
                    ->extraAttributes(['class' => 'kc-tb-p2'])
                    ->toHtml()
                : '',
            fn (): string => ControlMatrix::canAccess()
                ? self::pageButton('controlMatrix', ControlMatrix::getNavigationLabel(), ControlMatrix::getNavigationIcon(), ControlMatrix::getUrl(), ControlMatrix::getRouteName())
                    ->extraAttributes(['class' => 'kc-tb-p4'])
                    ->toHtml()
                : '',
            fn (): string => self::moreMenu((int) $user->getKey())->toHtml(),
        ];

        $html = '';

        // Ust cubuk her sayfada cizilir: bir dugme cozulemezse (ornegin
        // bilesen onbellegi eski, rota yok) yalniz o dugme dusar, panel acilir.
        foreach ($parts as $part) {
            try {
                $html .= $part();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return new HtmlString($html);
    }

    private static function pageButton(string $name, string $label, string | BackedEnum | Htmlable | null $icon, string $url, string $routeName): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->url($url)
            ->button()
            ->size('xs')
            ->labeledFrom('xl')
            ->tooltip($label)
            ->color(request()->routeIs($routeName) ? 'primary' : 'gray');
    }

    /** Zilin yanindaki simge dugmesi (Tum bildirimleri gor). */
    private static function iconButton(string $name, string $label, Heroicon $icon, string $url, string $routePattern): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->url($url)
            ->iconButton()
            ->tooltip($label)
            ->color(request()->routeIs($routePattern) ? 'primary' : 'gray');
    }

    /** Hizli islemler dairesi: sabitler + kisinin secimleri + "Hizli islem ekle". */
    private static function quickActions(int $personnelId): string
    {
        return view('filament.components.quick-actions', [
            'actions' => app(QuickActionCatalog::class)->forPersonnel($personnelId),
            'manageUrl' => QuickActionSettings::getUrl(),
            'label' => __('quick_action.button'),
            'addLabel' => __('quick_action.add'),
        ])->render();
    }

    /**
     * Sablon menusunun icerigi; ozellik kapaliysa ya da kisi dokumanlari
     * goremiyorsa null (istek basina bir kez hesaplanir).
     *
     * @return array<string, mixed>|null
     */
    private static function templateMenu(Personnel $user): ?array
    {
        static $cache = [];

        if (! FeatureFlags::enabled(Feature::TopbarTemplates) || ! DocumentResource::canAccess()) {
            return null;
        }

        return $cache[(int) $user->getKey()] ??= app(TemplateMenu::class)->for($user);
    }

    /**
     * Uc nokta menusu: ust cubuktaki kisayollarin aynisi, uc bolum - sayfalar,
     * hizli islemler, dil. Ogeler her zaman cizilir; hangisinin gorunecegini
     * ekran genisligi secer (konelsis.css). Ayni sayfayi acabilme kurallari.
     */
    private static function moreMenu(int $personnelId): ActionGroup
    {
        $pages = [];

        if (NotificationResource::canAccess()) {
            $pages[] = self::menuItem('moreAllNotifications', __('notification_inbox.open_all'), Heroicon::OutlinedQueueList, NotificationResource::getUrl(), 2)
                ->color(request()->routeIs(NotificationResource::getRouteBaseName().'.*') ? 'primary' : 'gray');
        }

        if (WorkBoard::canAccess()) {
            $pages[] = self::menuItem('moreWorkBoard', WorkBoard::getNavigationLabel(), WorkBoard::getNavigationIcon(), WorkBoard::getUrl(), 2)
                ->color(request()->routeIs(WorkBoard::getRouteName()) ? 'primary' : 'gray');
        }

        if (ControlMatrix::canAccess()) {
            $pages[] = self::menuItem('moreControlMatrix', ControlMatrix::getNavigationLabel(), ControlMatrix::getNavigationIcon(), ControlMatrix::getUrl(), 4)
                ->color(request()->routeIs(ControlMatrix::getRouteName()) ? 'primary' : 'gray');
        }

        $quick = [];

        if (QuickActionSettings::canAccess()) {
            foreach (app(QuickActionCatalog::class)->forPersonnel($personnelId) as $index => $action) {
                $quick[] = self::menuItem('moreQuickAction'.$index, $action->label, $action->icon, $action->url, 1);
            }

            $quick[] = self::menuItem('moreQuickActionAdd', __('quick_action.add'), Heroicon::OutlinedPlus, QuickActionSettings::getUrl(), 1);
        }

        $current = app()->getLocale();
        $languages = [];

        foreach (['tr' => 'Türkçe', 'en' => 'English'] as $locale => $label) {
            $languages[] = self::menuItem('moreLocale'.ucfirst($locale), $label, Heroicon::OutlinedLanguage, request()->fullUrlWithQuery(['locale' => $locale]), 4)
                ->color($current === $locale ? 'primary' : 'gray');
        }

        $sections = array_values(array_filter([$pages, $quick, $languages]));

        return ActionGroup::make(array_map(
            fn (array $actions): ActionGroup => ActionGroup::make($actions)->dropdown(false),
            $sections,
        ))
            ->label(__('app.more'))
            ->tooltip(__('app.more'))
            ->icon(Heroicon::EllipsisVertical)
            ->iconButton()
            ->color('gray')
            ->dropdownPlacement('bottom-end')
            ->dropdownMaxHeight('75vh')
            ->extraDropdownAttributes(['class' => 'kc-tb-more']);
    }

    private static function menuItem(string $name, string $label, string | BackedEnum | Htmlable | null $icon, string $url, int $priority): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->url($url)
            ->color('gray')
            ->extraAttributes(['class' => 'kc-tb-more-p'.$priority]);
    }
}
