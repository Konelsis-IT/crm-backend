<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\Approval\UnresolvedReason;

/**
 * Onayci cozumlemesinin sonucu: bulunan personel kimlikleri ya da neden
 * bulunamadigi.
 */
final readonly class ResolvedApprovers
{
    /**
     * @param  list<int>  $personnelIds
     */
    private function __construct(
        public array $personnelIds,
        public ?UnresolvedReason $reason,
    ) {}

    /**
     * @param  list<int>  $personnelIds
     */
    public static function of(array $personnelIds): self
    {
        return new self(array_values(array_unique($personnelIds)), null);
    }

    public static function none(UnresolvedReason $reason): self
    {
        return new self([], $reason);
    }

    public function isEmpty(): bool
    {
        return $this->personnelIds === [];
    }
}
