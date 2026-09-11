<?php

declare(strict_types=1);

namespace App\Exceptions\Personnel;

use App\Exceptions\AbstractException;

/**
 * Kayit kendi ust kaydi olamaz.
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class SelfParentNotAllowedException extends AbstractException
{
    protected $code = 4002;
}
