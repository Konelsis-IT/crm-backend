<?php

declare(strict_types=1);

namespace App\Services\Approval\Subjects;

/**
 * Onaya konu kaydin, motorun ihtiyac duydugu ozeti: hash (karar aninda
 * yeniden dogrulanir), ekranda gosterilecek etiket/baglanti ve onayci
 * cozumlemesi icin baglam (sahip, birim, proje, tutar).
 */
final readonly class SubjectContext
{
    public function __construct(
        public string $hash,
        public string $label,
        public ?string $url = null,
        public ?int $ownerPersonnelId = null,
        public ?int $orgUnitId = null,
        public ?int $projectId = null,
        public ?float $amount = null,
        public ?string $currencyCode = null,
    ) {}
}
