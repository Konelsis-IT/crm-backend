<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Onay icin icerik bos: gorsel/video ya da metin govdesi yok. */
final class EmptyContentException extends AbstractException
{
    protected $code = 4710;
}
