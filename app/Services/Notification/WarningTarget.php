<?php

declare(strict_types=1);

namespace App\Services\Notification;

/**
 * Panodan acil uyarisi gonderilen kayit (D-208): kayit turu, adi, baglantisi
 * ve varsayilan alicilari (ilgili personel ve departmanlar). Sunucu kaydi
 * kendisi cozer; arayuzden gelen alici listesine guvenilmez.
 */
final readonly class WarningTarget
{
    /**
     * @param  list<int>  $personnelIds  Ilgili personel (sorumlu, talep eden, onayci)
     * @param  list<int>  $unitIds  Ilgili departmanlar (organizasyon birimi)
     */
    public function __construct(
        public string $subjectType,
        public int $subjectId,
        public string $title,
        public ?string $url,
        public array $personnelIds,
        public array $unitIds,
    ) {}
}
