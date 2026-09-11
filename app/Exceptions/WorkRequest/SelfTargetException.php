<?php

declare(strict_types=1);

namespace App\Exceptions\WorkRequest;

use App\Exceptions\AbstractException;

/** Kisi kendisine talep acamaz. */
final class SelfTargetException extends AbstractException
{
    protected $code = 4502;
}
