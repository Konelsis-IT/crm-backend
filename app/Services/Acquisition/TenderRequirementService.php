<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Services\AbstractService;

/**
 * TenderRequirement servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class TenderRequirementService extends AbstractService
{
    protected string $model = \App\Models\Acquisition\TenderRequirement::class;
}
