<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Services\AbstractService;

/**
 * Hukuki tutmaya eklenen doküman servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class LegalHoldDocumentService extends AbstractService
{
    protected string $model = \App\Models\Document\LegalHoldDocument::class;
}
