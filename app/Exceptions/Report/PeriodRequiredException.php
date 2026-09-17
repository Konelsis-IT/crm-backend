<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Donem secilmemis. */
final class PeriodRequiredException extends AbstractException
{
    protected $code = 4604;
}
