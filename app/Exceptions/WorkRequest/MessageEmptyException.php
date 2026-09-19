<?php

declare(strict_types=1);

namespace App\Exceptions\WorkRequest;

use App\Exceptions\AbstractException;

/** Bos yazisma mesaji. */
final class MessageEmptyException extends AbstractException
{
    protected $code = 4508;
}
