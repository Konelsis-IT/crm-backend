<?php

declare(strict_types=1);

namespace App\Query\Notification;

use App\Models\Notification\PanelNotification;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tum bildirimler tablosu (D-122): oturumdaki kisinin zil bildirimleri.
 */
final class NotificationInboxQueries
{
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

    public function applyUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function unreadCount(?Personnel $personnel): int
    {
        return $this->applyUnread($this->applyRecipient(PanelNotification::query(), $personnel))->count();
    }

    /** Masaustu bildirimi / ses (D-126): kisinin en son okunmamis bildirimi. */
    public function latestUnread(?Personnel $personnel): ?PanelNotification
    {
        return $this->applyUnread($this->applyRecipient(PanelNotification::query(), $personnel))
            ->latest('created_at')
            ->first();
    }
}
