<?php

declare(strict_types=1);

namespace App\Exceptions\WorkRequest;

use App\Exceptions\AbstractException;

/** Onaya tabi talep icin belirlenen onay mercii politikasi (WORK_REQUEST_DESIGNATED) tanimli degil. */
final class ApprovalPolicyMissingException extends AbstractException
{
    protected $code = 4506;
}
