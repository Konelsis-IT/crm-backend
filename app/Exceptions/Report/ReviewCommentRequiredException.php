<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Revizyon/ret aciklamasi zorunlu. */
final class ReviewCommentRequiredException extends AbstractException
{
    protected $code = 4607;
}
