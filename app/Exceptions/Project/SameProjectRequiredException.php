<?php

declare(strict_types=1);

namespace App\Exceptions\Project;

use App\Exceptions\AbstractException;

/**
 * Baglanan kayitlar ayni projeye ait olmali.
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class SameProjectRequiredException extends AbstractException
{
    protected $code = 4097;
}
