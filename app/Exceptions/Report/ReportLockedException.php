<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Rapor bu durumda degistirilemez. */
final class ReportLockedException extends AbstractException
{
    protected $code = 4608;
}
