<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Dosya turu desteklenmiyor, dosya bos/bozuk ya da surum siniri doldu. */
final class UnsupportedMediaException extends AbstractException
{
    protected $code = 4705;
}
