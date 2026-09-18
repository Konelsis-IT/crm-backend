<?php

declare(strict_types=1);

namespace App\Filament\Resources\SocialContents;

use App\Filament\Resources\SocialContents\Pages\ManageSocialMedia;
use App\Models\SocialMedia\SocialContent;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

/**
 * Sosyal Medya (B31, D-106): menude TEK oge; icerik akisi, plan, ilham ve
 * rakipler, analiz ve ayarlar ayni sayfadaki React uygulamasinin icindedir
 * (18 Eylul 2026 kullanici karari: "sayfa Filament resource olacak ama icerigi
 * tamamen React tasarimi olacak").
 *
 * - Form / tablo YOKTUR: kayit islemleri filament.admin.social.* JSON uclari
 *   uzerinden servis katmanina gider (AdminPanelProvider).
 * - Menude ust seviyede, Raporlar'in hemen ardinda: ust bloktaki ogeler -2 / -1
 *   sirasindadir (Raporlar -1 ve blogun sonuncusu); 0 onlardan sonra, menu
 *   gruplarindan once gelir.
 * - Menu rozeti yoktur: canAccess() her Livewire isteginde calisir, ucuz kalir.
 * - `$recordTitleAttribute` tanimli degildir: genel aramaya girmez.
 */
class SocialContentResource extends Resource
{
    protected static ?string $model = SocialContent::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('social_content.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('social_content.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('social_content.navigation');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('social_media.admin_ui')
            && SchemaReadiness::hasBatch('B31')
            && parent::canAccess();
    }

    public static function getPages(): array
    {
        return [
            // Anahtar 'index' olmak zorunda; aksi halde menu ogesi uretilmez.
            'index' => ManageSocialMedia::route('/'),
        ];
    }
}
