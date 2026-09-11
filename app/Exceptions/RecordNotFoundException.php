<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class RecordNotFoundException extends AbstractException
{
    protected $code = 4040;
}
