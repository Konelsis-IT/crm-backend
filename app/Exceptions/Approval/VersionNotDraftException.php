<?php

declare(strict_types=1);

namespace App\Exceptions\Approval;

use App\Exceptions\AbstractException;

/**
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir (kural S-1).
 */
final class VersionNotDraftException extends AbstractException
{
    protected $code = 4210;
}
