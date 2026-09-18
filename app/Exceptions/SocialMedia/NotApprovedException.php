<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Yalniz onaylanmis icerik paylasildi olarak isaretlenebilir. */
final class NotApprovedException extends AbstractException
{
    protected $code = 4702;
}
