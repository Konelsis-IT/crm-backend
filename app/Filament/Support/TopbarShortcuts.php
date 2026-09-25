<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Pages\QuickActionSettings;
use App\Filament\Pages\Work\ControlMatrix;
use App\Filament\Pages\Work\WorkBoard;
use App\Filament\Resources\Notifications\NotificationResource;
use App\Filament\Support\QuickActions\QuickActionCatalog;
use App\Models\Personnel\Personnel;
use BackedEnum;
use Filament\Actions\Action;
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
                ? self::iconButton('allNotifications', __('notification_inbox.open_all'), Heroicon::OutlinedQueueList, NotificationResource::getUrl(), NotificationResource::getRouteBaseName().'.*')->toHtml()
                : '',
            fn (): string => self::quickActions((int) $user->getKey()),
            // Simgeler sayfanin kendi simgesidir (24 Eylul 2026: daha anlasilir
            // simgeler - Is panosu is listesi panosu, Kontrol matrisi kontrol listesi).
            fn (): string => WorkBoard::canAccess()
                ? self::pageButton('workBoard', WorkBoard::getNavigationLabel(), WorkBoard::getNavigationIcon(), WorkBoard::getUrl(), WorkBoard::getRouteName())->toHtml()
                : '',
            fn (): string => ControlMatrix::canAccess()
                ? self::pageButton('controlMatrix', ControlMatrix::getNavigationLabel(), ControlMatrix::getNavigationIcon(), ControlMatrix::getUrl(), ControlMatrix::getRouteName())->toHtml()
                : '',
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
            ->labeledFrom('lg')
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
}
