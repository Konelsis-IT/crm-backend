<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Zengin metin govdesi izin verilen boyutu asiyor (:max). */
final class BodyTooLargeException extends AbstractException
{
    protected $code = 4713;
}
