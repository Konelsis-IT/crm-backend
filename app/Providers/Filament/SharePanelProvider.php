<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Share\Pages\SharedDocument;
use App\Http\Controllers\Share\SharedRevisionFileController;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Paylasim paneli (D-75): kimlik dogrulamasi olmayan, tek sayfalik
 * "paylasilan belge" gorunumu. Menusu, girisi ve kaynagi yoktur; yalniz
 * /share/documents/{token} sayfasi ve dosya ucu. Yetki/sifre mekanizmasi
 * sonraki turda (02 SS11) bu panele eklenir.
 */
class SharePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('share')
            ->path('share')
            ->brandName(fn (): string => __('app.name'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigation(false)
            ->topNavigation()
            ->pages([
                SharedDocument::class,
            ])
            // Paylasilan belgenin dosyasi: filament.share.shared-file
            ->routes(fn (Panel $panel) => Route::get('documents/{token}/file', SharedRevisionFileController::class)->name('shared-file'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([]);
    }
}
