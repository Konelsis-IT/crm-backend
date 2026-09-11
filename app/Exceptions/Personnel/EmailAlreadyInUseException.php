<?php

declare(strict_types=1);

namespace App\Exceptions\Personnel;

use App\Exceptions\AbstractException;

/**
 * E-posta adresi zaten kullaniliyor.
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class EmailAlreadyInUseException extends AbstractException
{
    protected $code = 4001;
}
