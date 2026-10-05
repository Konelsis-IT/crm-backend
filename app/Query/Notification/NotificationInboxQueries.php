<?php

declare(strict_types=1);

namespace App\Query\Notification;

use App\Models\Notification\PanelNotification;
use App\Models\Personnel\Personnel;
use App\Services\Authorization\SystemAccount;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tum bildirimler tablosu (D-122): oturumdaki kisinin zil bildirimleri.
 *
 * D-149 (30 Eylul 2026 kullanici istegi: "Sistem yoneticisi olarak tum
 * bildirimler alanina girdigimde tum bildirimleri gormeliyim"): gizli sistem
 * hesabi kendi oturumunda butun personelin bildirimlerini gorur. Personel
 * degistirirken tablo secilen kisinin bildirimlerini gosterir; digerleri
 * yalniz kendi bildirimlerini gorur. Okundu / okunmadi isaretleme her zaman
 * yalniz kisinin kendi bildirimlerine uygulanir (applyRecipient).
 */
final class NotificationInboxQueries
{
    public function __construct(private readonly SystemAccount $systemAccount) {}

    /** Yalniz kisinin kendi bildirimleri. */
    public function applyRecipient(Builder $query, ?Personnel $personnel): Builder
    {
        if (! $personnel instanceof Personnel) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('notifiable_type', $personnel->getMorphClass())
            ->where('notifiable_id', $personnel->getKey());
    }

    /** Tablodaki herkesin bildirimini goren hesap mi (yalniz gizli sistem hesabi). */
    public function seesAll(?Personnel $viewer): bool
    {
        return $viewer instanceof Personnel && $this->systemAccount->is($viewer);
    }

    /** Tablonun kayitlari: sistem hesabinda butun personelin (alici ile), digerlerinde kisinin kendi bildirimleri. */
    public function applyVisible(Builder $query, ?Personnel $viewer): Builder
    {
        if (! $this->seesAll($viewer)) {
            return $this->applyRecipient($query, $viewer);
        }

        return $query
            ->where('notifiable_type', (new Personnel)->getMorphClass())
            ->with('notifiable');
    }

    /** "Alici" suzgeci: secilen personelin bildirimleri. */
    public function applyRecipientId(Builder $query, ?int $personnelId): Builder
    {
        if ($personnelId === null) {
            return $query;
        }

        return $query
            ->where('notifiable_type', (new Personnel)->getMorphClass())
            ->where('notifiable_id', $personnelId);
    }

    public function applyUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function unreadCount(?Personnel $personnel): int
    {
        return $this->applyUnread($this->applyRecipient(PanelNotification::query(), $personnel))->count();
    }

    /** Tablodaki okunmamis sayisi (sistem hesabinda herkesinki). */
    public function visibleUnreadCount(?Personnel $viewer): int
    {
        return $this->applyUnread($this->applyVisible(PanelNotification::query(), $viewer))->count();
    }

    /** Masaustu bildirimi / ses (D-126): kisinin en son okunmamis bildirimi. */
    public function latestUnread(?Personnel $personnel): ?PanelNotification
    {
        return $this->applyUnread($this->applyRecipient(PanelNotification::query(), $personnel))
            ->latest('created_at')
            ->first();
    }
}
