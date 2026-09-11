<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Kod zaten kullaniliyor.
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class CodeAlreadyInUseException extends AbstractException
{
    protected $code = 4092;
}
