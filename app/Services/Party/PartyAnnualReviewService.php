<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Services\AbstractService;

/**
 * PartyAnnualReview servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class PartyAnnualReviewService extends AbstractService
{
    protected string $model = \App\Models\Party\PartyAnnualReview::class;
}
