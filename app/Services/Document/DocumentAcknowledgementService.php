<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Services\AbstractService;

/**
 * Revizyon okundu/kabul teyidi servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class DocumentAcknowledgementService extends AbstractService
{
    protected string $model = \App\Models\Document\DocumentAcknowledgement::class;
}
