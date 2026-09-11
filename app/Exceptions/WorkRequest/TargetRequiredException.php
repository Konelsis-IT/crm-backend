<?php

declare(strict_types=1);

namespace App\Exceptions\WorkRequest;

use App\Exceptions\AbstractException;

/** Muhatap kisi ya da birim secilmemis. */
final class TargetRequiredException extends AbstractException
{
    protected $code = 4501;
}
