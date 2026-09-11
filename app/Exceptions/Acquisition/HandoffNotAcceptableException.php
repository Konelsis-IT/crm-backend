<?php

declare(strict_types=1);

namespace App\Exceptions\Acquisition;

use App\Exceptions\AbstractException;

/**
 * Operasyona devir kabul kosullari saglanmiyor (:reason).
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class HandoffNotAcceptableException extends AbstractException
{
    protected $code = 4094;
}
