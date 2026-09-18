<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Islem yalniz gorsellerde yapilabilir (video ya da baska tur secildi). */
final class MediaNotImageException extends AbstractException
{
    protected $code = 4707;
}
