<?php

declare(strict_types=1);

namespace App\Exceptions\WorkRequest;

use App\Exceptions\AbstractException;

/** Talep bu kisiye ya da birime yonlendirilemez. */
final class ForwardNotAllowedException extends AbstractException
{
    protected $code = 4509;
}
