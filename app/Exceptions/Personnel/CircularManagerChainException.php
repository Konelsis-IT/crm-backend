<?php

declare(strict_types=1);

namespace App\Exceptions\Personnel;

use App\Exceptions\AbstractException;

/**
 * Bir personel, dogrudan veya zincirleme olarak kendi altindaki birine
 * raporlayamaz.
 */
final class CircularManagerChainException extends AbstractException
{
    protected $code = 4002;
}
