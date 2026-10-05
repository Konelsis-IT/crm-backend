<?php

declare(strict_types=1);

namespace App\Exceptions\Platform;

use App\Exceptions\AbstractException;

/** Bu surum zaten canlidaki yayin surumu. */
final class ReleaseAlreadyPublishedException extends AbstractException
{
    protected $code = 4902;
}
