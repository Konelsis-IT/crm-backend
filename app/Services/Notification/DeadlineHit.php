<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\Notification\AlertSeverity;
use Carbon\CarbonImmutable;

/**
 * Son tarih taramasinin bulgusu: hangi kayit, ne zaman, kimin sorumlulugunda.
 */
final readonly class DeadlineHit
{
    /**
     * @param  array<string, mixed>  $params  Baslik yer tutuculari (hassas veri yok)
     */
    public function __construct(
        public string $triggerCode,
        public string $subjectType,
        public int $subjectId,
        public CarbonImmutable $dueAt,
        public array $params,
        public ?int $ownerPersonnelId = null,
        public ?int $projectId = null,
        public ?int $orgUnitId = null,
        public ?string $url = null,
    ) {}

    public function dedupeKey(AlertSeverity $severity): string
    {
        return sprintf('%s:%d:%s', $this->triggerCode, $this->subjectId, $severity->value);
    }

    public function subjectKey(): string
    {
        return sprintf('%s:%d', $this->triggerCode, $this->subjectId);
    }

    public function titleKey(): string
    {
        // Dil anahtarinda nokta ic ice dizi demektir; tetikleyici kodu alt cizgiyle yazilir.
        return 'business_alert.triggers.'.str_replace('.', '_', $this->triggerCode);
    }

    /** Bugune gore kalan gun (gecmisse negatif). */
    public function daysLeft(CarbonImmutable $now): int
    {
        return (int) $now->startOfDay()->diffInDays($this->dueAt->startOfDay(), false);
    }
}
