<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Kayit baskasi tarafindan degistirildi.
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class StaleRecordException extends AbstractException
{
    protected $code = 4090;
}
