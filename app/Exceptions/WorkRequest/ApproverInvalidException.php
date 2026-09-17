<?php

declare(strict_types=1);

namespace App\Exceptions\WorkRequest;

use App\Exceptions\AbstractException;

/** Onay mercii talep eden ya da muhatap olamaz. */
final class ApproverInvalidException extends AbstractException
{
    protected $code = 4505;
}
