<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\Platform\Feature;

/**
 * Ozellik acik mi? (D-128, 25 Eylul 2026 kullanici karari). Durum yalniz
 * veritabanindaki `features` tablosundan okunur (FeatureRegistry); eskiden
 * kullanilan config/features.php ve FEATURE_* ortam degiskenleri kaldirildi.
 *
 * Her ekran, sekme, kisayol ve uc kendi ozelligine bakar:
 * `FeatureFlags::enabled(Feature::WorkBoard)`. Katalog: App\Enums\Platform\Feature.
 */
final class FeatureFlags
{
    public static function enabled(Feature $feature): bool
    {
        return app(FeatureRegistry::class)->enabled($feature);
    }
}
