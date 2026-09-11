<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Ayni benzersiz anahtarla ikinci kez kayit denendi.
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class DuplicateRecordException extends AbstractException
{
    protected $code = 4093;
}
