<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\Activity\ActivityChannel;

final readonly class ActivityInput
{
    /**
     * @param  string  $subjectType  Kayit turu (reference_types listesinden).
     * @param  array<string, mixed>|null  $changes  Degisen alanlar; hassas anahtarlar gizlenir.
     */
    public function __construct(
        public string $subjectType,
        public int|string $subjectId,
        public string $actionCode,
        public ?array $changes = null,
        public ActivityChannel $channel = ActivityChannel::Panel,
    ) {}
}
