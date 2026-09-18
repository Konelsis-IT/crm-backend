<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Dosya izin verilen boyutu asiyor (:max). */
final class FileTooLargeException extends AbstractException
{
    protected $code = 4712;
}
