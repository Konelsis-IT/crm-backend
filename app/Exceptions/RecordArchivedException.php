<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Kayit arsivde (D-156): once arsivden cikarilmadan degistirilemez.
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class RecordArchivedException extends AbstractException
{
    protected $code = 4099;
}
