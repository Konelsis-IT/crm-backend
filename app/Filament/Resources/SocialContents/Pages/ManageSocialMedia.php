<?php

declare(strict_types=1);

namespace App\Filament\Resources\SocialContents\Pages;

use App\Filament\Resources\SocialContents\SocialContentResource;
use App\Filament\Support\SocialAppConfig;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Sosyal Medya sayfasi (B31, D-106): govdenin tamami React uygulamasidir.
 *
 * - Gorunum yalniz stil baglantisini ve `wire:ignore` altindaki kok ogeyi
 *   icerir; betikler sayfaya ozel BODY_END kancasindan gelir
 *   (AdminPanelProvider -> filament.social.scripts), boylece React'ten SONRA
 *   calisirlar.
 * - Filament basligi, alt basligi ve gezinti izi bos birakilir: ust cubugu
 *   (modul adi, hesap secimi, "Icerik ekle") React cizer. Sekme basligi
 *   getTitle() ile yine Turkce gelir.
 * - Sorgu zinciri yoktur; yapilandirma SocialAppConfig'ten alinir. Yetki,
 *   kaynagin canAccess() kontrolu (mount + hydrate) ve JSON uclarindaki
 *   Gate::authorize ile saglanir.
 */
class ManageSocialMedia extends Page
{
    protected static string $resource = SocialContentResource::class;

    // Filament 5'te $view statik DEGILDIR (BasePage::$view).
    protected string $view = 'filament.social.app';

    public function getTitle(): string | Htmlable
    {
        return __('social_content.title');
    }

    /** Baslik alanini React cizer. */
    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    /**
     * Tek sayfali kaynak: "Sosyal Medya > Sosyal Medya" izi gereksiz; iz bos
     * olunca Filament baslik blogunu hic cizmez.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'config' => SocialAppConfig::make(),
        ];
    }
}
