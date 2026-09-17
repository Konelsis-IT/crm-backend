<?php

declare(strict_types=1);

namespace App\Exceptions\WorkRequest;

use App\Exceptions\AbstractException;

/** Onaya tabi talepte onay mercii secilmeli. */
final class ApproverRequiredException extends AbstractException
{
    protected $code = 4504;
}
