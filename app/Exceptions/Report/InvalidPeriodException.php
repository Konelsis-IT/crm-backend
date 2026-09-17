<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Bitis tarihi baslangictan once. */
final class InvalidPeriodException extends AbstractException
{
    protected $code = 4605;
}
