<?php

declare(strict_types=1);

namespace App\Services\Procurement;

/**
 * Bir siparis kaleminin onay durumu (D-189): onay gerekiyor mu, saglandi mi,
 * TL tutari, onaylamasi gereken merciler (ad ve gorev) ve eksik olan sey
 * (onerilen teklif, TL kuru, onay politikasi). Ekrandaki uyari penceresi ve
 * servis kurali ayni nesneyi kullanir.
 */
final readonly class PurchaseApprovalState
{
    public const MISSING_QUOTE = 'quote';

    public const MISSING_RATE = 'rate';

    public const MISSING_POLICY = 'policy';

    /**
     * @param  list<string>  $approvers  "Ad Soyad (Gorev)" bicimi.
     */
    public function __construct(
        public bool $required,
        public bool $satisfied,
        public bool $pending,
        public ?float $amountTry,
        public array $approvers,
        public ?string $policyName,
        public ?string $missing = null,
        public bool $selfApprovable = false,
    ) {}

    public static function notRequired(): self
    {
        return new self(false, true, false, null, [], null);
    }

    public function approversText(): string
    {
        return $this->approvers === [] ? '–' : implode(', ', $this->approvers);
    }
}
