<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Services\AbstractService;

/**
 * Doküman baglantisi servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class DocumentLinkService extends AbstractService
{
    protected string $model = \App\Models\Document\DocumentLink::class;
}
