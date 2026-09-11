<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Services\AbstractService;

/**
 * Revizyon inceleme/onay karari servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class DocumentReviewService extends AbstractService
{
    protected string $model = \App\Models\Document\DocumentReview::class;
}
