<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Services\AbstractService;

/**
 * Revizyona bagli dosya servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class DocumentRevisionFileService extends AbstractService
{
    protected string $model = \App\Models\Document\DocumentRevisionFile::class;
}
