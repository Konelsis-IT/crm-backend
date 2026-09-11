<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Izin verilmeyen durum gecisi.
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class InvalidTransitionException extends AbstractException
{
    protected $code = 4091;
}
