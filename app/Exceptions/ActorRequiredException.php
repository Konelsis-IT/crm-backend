<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir (kural S-1).
 */
final class ActorRequiredException extends AbstractException
{
    protected $code = 4010;
}
