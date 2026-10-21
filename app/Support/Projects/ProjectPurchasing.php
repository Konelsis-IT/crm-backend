<?php

declare(strict_types=1);

namespace App\Support\Projects;

use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;

/**
 * Proje sayfasinin satin alma baglantisi (D-196, 12 Ekim 2026; kullanici:
 * "tabi bu konuyu düzenle, öyle bırakma"). Eski proje tedarik kalemleri
 * (D-68, project_supply_items) yeni satin alma modulunu (D-189/D-192)
 * tekrarliyordu. Anahtar acikken:
 *
 * - Satin Alma adiminin "Tedarik kalemleri" tablosu yerine projenin Satin
 *   alinacaklar'i ("Satın alma"), Lojistik adiminin "Sevkiyat ve teslimat"
 *   tablosu yerine projenin siparisleri ("Lojistik"), Yazilim adiminin
 *   tablosu yerine projenin yazilim kalemleri ("Yazılım") gorunur.
 * - Adim hazirligindaki tedarik beklentileri (ExpectationKind::SupplyItems,
 *   SupplyOrdered, SupplyDelivered, SoftwareItems) satin alma kayitlarini ve
 *   eski tedarik kalemlerini birlikte sayar.
 * - Eski tedarik kalemleri silinmez: varsa ilgili tablonun altinda kapali
 *   "Eski tedarik kayıtları" bolumunde salt okunur gorunur.
 *
 * Anahtar tek yerdedir: Satin alinacaklar ozelligi (surum 2.9'a kadar canlida, D-229
 * kapali; FeatureRegistry yayinlanmamis surumu gizler) ve B53 (kalemli talep,
 * satin alma teklifleri, siparisler). Kapaliyken proje sayfasi ve hazirlik
 * bugunku gibi eski tedarik kalemleriyle calisir.
 */
final class ProjectPurchasing
{
    /** Proje sayfasi satin alma kayitlarina bagli mi? */
    public static function enabled(): bool
    {
        return FeatureFlags::enabled(Feature::PurchaseNeeds) && SchemaReadiness::hasBatch('B53');
    }

    /** Lojistik tablosu (siparisler) da gorunur mu? Siparisler kendi ozelligine baglidir. */
    public static function ordersEnabled(): bool
    {
        return self::enabled() && FeatureFlags::enabled(Feature::PurchaseOrders);
    }

    /** Eski tedarik kalemleri (Feature::SupplyItems) ekranda gosterilebilir mi? */
    public static function legacyEnabled(): bool
    {
        return FeatureFlags::enabled(Feature::SupplyItems);
    }
}
