<?php

declare(strict_types=1);

namespace App\Exceptions\Platform;

use App\Exceptions\AbstractException;

/** Daha eski bir surum yayinlanmak istendi (geri alma); acik onay gerekir. */
final class ReleaseDowngradeException extends AbstractException
{
    protected $code = 4903;
}
