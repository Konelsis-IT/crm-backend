<?php

declare(strict_types=1);

namespace App\Exceptions\Platform;

use App\Exceptions\AbstractException;

/** Yayinlanacak surum "2.4" ya da "2.4.1" biciminde degil. */
final class InvalidReleaseVersionException extends AbstractException
{
    protected $code = 4901;
}
