<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Services\AbstractService;

/**
 * Teslim tutanagi kalemi servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class TransmittalItemService extends AbstractService
{
    protected string $model = \App\Models\Document\TransmittalItem::class;
}
