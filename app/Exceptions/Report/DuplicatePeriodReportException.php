<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Ayni donem icin ikinci rapor. */
final class DuplicatePeriodReportException extends AbstractException
{
    protected $code = 4606;
}
