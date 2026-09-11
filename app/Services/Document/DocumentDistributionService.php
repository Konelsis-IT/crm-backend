<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Services\AbstractService;

/**
 * Revizyon dagitim kaydi servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class DocumentDistributionService extends AbstractService
{
    protected string $model = \App\Models\Document\DocumentDistribution::class;
}
