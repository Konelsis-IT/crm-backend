<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Taslagin bagli olmasi gereken kayit secilmemis. */
final class SubjectRequiredException extends AbstractException
{
    protected $code = 4602;
}
