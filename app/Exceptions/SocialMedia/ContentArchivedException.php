<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Arsivdeki icerik degistirilemez. */
final class ContentArchivedException extends AbstractException
{
    protected $code = 4709;
}
