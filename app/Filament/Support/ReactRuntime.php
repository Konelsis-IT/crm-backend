<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;

/**
 * React calisma zamaninin (react + react-dom 18.3.1 UMD) sayfaya kim
 * tarafindan kondugunu tek yerden soyler.
 *
 * Sohbet baslaticisi (D-83) panelin her sayfasinda BODY_END kancasinda React'i
 * yukler. Sosyal Medya sayfasi (D-106) ayni sayfada ikinci bir React kopyasi
 * yuklerse iki ayri React ornegi olusur (hook'lar bozulur). Bu yuzden hem
 * AdminPanelProvider'daki sohbet kancasi hem de
 * resources/views/filament/social/scripts.blade.php ayni kosula bakar:
 * sohbet React'i getiriyorsa sosyal betikler onu yeniden eklemez.
 */
final class ReactRuntime
{
    /**
     * Sohbet baslaticisi bu istekte sayfaya konacak mi (dolayisiyla React'i
     * o mu getirecek)? Kosul: oturum acik, B12A uygulanmis, bayrak acik.
     */
    public static function providedByChat(): bool
    {
        return auth()->check()
            && SchemaReadiness::hasBatch('B12A')
            && FeatureFlags::enabled('chat.admin_ui');
    }
}
