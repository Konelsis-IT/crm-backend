<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Notification\PanelNotification;
use App\Models\Personnel\Personnel;
use App\Query\Notification\NotificationInboxQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Support\Carbon;

/**
 * Tum bildirimler (D-122): okundu / okunmadi isaretleme. Yalniz kisinin
 * kendi bildirimlerine dokunur. Kisinin kendi okuma durumu oldugu icin
 * Personel Hareketleri'ne satir yazilmaz (zil panelindeki davranisla ayni).
 */
final class NotificationInboxService extends AbstractService
{
    protected string $model = PanelNotification::class;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly NotificationInboxQueries $queries,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /** @param  iterable<PanelNotification|string>  $records */
    public function markRead(Personnel $owner, iterable $records): int
    {
        return $this->mark($owner, $records, Carbon::now('UTC'));
    }

    /** @param  iterable<PanelNotification|string>  $records */
    public function markUnread(Personnel $owner, iterable $records): int
    {
        return $this->mark($owner, $records, null);
    }

    public function markAllRead(Personnel $owner): int
    {
        return $this->transactions->run(fn (): int => $this->queries
            ->applyUnread($this->queries->applyRecipient(PanelNotification::query(), $owner))
            ->update(['read_at' => Carbon::now('UTC')]));
    }

    /** @param  iterable<PanelNotification|string>  $records */
    private function mark(Personnel $owner, iterable $records, ?Carbon $readAt): int
    {
        $ids = [];

        foreach ($records as $record) {
            $ids[] = $record instanceof PanelNotification ? (string) $record->getKey() : (string) $record;
        }

        if ($ids === []) {
            return 0;
        }

        return $this->transactions->run(fn (): int => $this->queries
            ->applyRecipient(PanelNotification::query(), $owner)
            ->whereKey($ids)
            ->update(['read_at' => $readAt]));
    }
}
