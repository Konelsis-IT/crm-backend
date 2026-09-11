<?php

declare(strict_types=1);

namespace App\Exceptions\Acquisition;

use App\Exceptions\AbstractException;

/**
 * Durum gecisinin on kosulu saglanmiyor (:reason).
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class GuardNotSatisfiedException extends AbstractException
{
    protected $code = 4095;
}
