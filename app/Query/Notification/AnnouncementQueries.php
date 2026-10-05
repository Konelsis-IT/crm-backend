<?php

declare(strict_types=1);

namespace App\Query\Notification;

use App\Models\Notification\Announcement;
use Illuminate\Database\Eloquent\Builder;

final class AnnouncementQueries
{
    /** Panodaki duyuru listesi: en yeni ustte. */
    public function latest(): Builder
    {
        return Announcement::query()
            ->with('sender')
            ->orderByDesc('sent_at');
    }

    /** Genel bakis duyuru kartlari (D-144 / D-150): en yeni $limit duyuru. */
    public function recent(int $limit): Builder
    {
        return $this->latest()->limit(max(1, $limit));
    }
}
