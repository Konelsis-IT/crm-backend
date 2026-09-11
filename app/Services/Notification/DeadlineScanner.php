<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\Notification\AlertSeverity;
use App\Models\Notification\BusinessAlert;
use App\Query\Notification\BusinessAlertQueries;
use App\Query\Notification\DeadlineQueries;
use App\Services\Platform\SchemaReadiness;
use Carbon\CarbonImmutable;

/**
 * Yaklasan / gecmis son tarih taramasi (D-82, "acil durumlarda yaklasan
 * tarihlerde bildirim"). Zamanlayici gunluk calistirir:
 *
 * - Ufuk: bugunden +7 gun; gecmis (tamamlanmamis) kayitlar 60 gune kadar.
 * - Seviye kalan gune gore: >3 gun uyari, 1-3 gun yuksek, bugun/gecmis kritik.
 * - Ayni kayit/seviye bir kez acilir; seviye yukselince yeni uyari acilir,
 *   eskisi kapanir. Konu artik bekleyen degilse (cozuldu, tarih ileri alindi)
 *   acik uyari kapatilir.
 */
final class DeadlineScanner
{
    public const HORIZON_DAYS = 7;

    public const OVERDUE_DAYS = 60;

    public function __construct(
        private readonly DeadlineQueries $deadlines,
        private readonly BusinessAlertQueries $alerts,
        private readonly BusinessAlertService $service,
    ) {}

    /**
     * @return array{hits: int, opened: int, notified: int, closed: int}
     */
    public function scan(?CarbonImmutable $now = null): array
    {
        $result = ['hits' => 0, 'opened' => 0, 'notified' => 0, 'closed' => 0];

        if (! SchemaReadiness::hasBatch('B11A')) {
            return $result;
        }

        $now ??= CarbonImmutable::now('UTC');
        $hits = $this->deadlines->hits($now->subDays(self::OVERDUE_DAYS), $now->addDays(self::HORIZON_DAYS));
        $result['hits'] = $hits->count();
        $activeKeys = [];

        foreach ($hits as $hit) {
            $activeKeys[$hit->subjectKey()] = true;
            $severity = AlertSeverity::forDaysLeft($hit->daysLeft($now));

            [$alert, $isNew] = $this->service->openFor($hit, $severity);

            if ($isNew) {
                $result['opened']++;
                $result['notified'] += $this->service->notify($alert);
            }
        }

        foreach ($this->alerts->openDeadlineAlerts() as $alert) {
            /** @var BusinessAlert $alert */
            $key = sprintf('%s:%d', $alert->trigger_code, $alert->subject_id);

            if (! isset($activeKeys[$key])) {
                $this->service->close($alert, 'resolved');
                $result['closed']++;
            }
        }

        return $result;
    }
}
