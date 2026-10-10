<?php

declare(strict_types=1);

namespace App\Support\Acquisition;

use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;

/**
 * Teklif kapsamindaki Maliyet listesi (D-181, 9 Ekim 2026): B51 uygulandiginda
 * ve `acquisition.proposals.cost_lists` ozelligi (2.5) acikken calisir.
 * Yukleme alani, kapsam kartindaki liste, ZIP klasoru ve dokuman sayfasindaki
 * baglanti bu kontrolle gorunur; kapaliyken yuklenmis belgeler Dokumanlar'da
 * kalir, teklif ekranlarinda gosterilmez.
 *
 * Maliyet satirlari (Excel -> kalem) henuz ayristirilmaz: kullanici ornek
 * dosyayi gonderince ayrica planlanacak.
 */
final class CostLists
{
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B51') && SchemaReadiness::hasBatch('B43') && FeatureFlags::enabled(Feature::ProposalCostLists);
    }
}
