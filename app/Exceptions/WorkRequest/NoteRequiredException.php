<?php

declare(strict_types=1);

namespace App\Exceptions\WorkRequest;

use App\Exceptions\AbstractException;

/** Ret icin gerekce zorunlu. */
final class NoteRequiredException extends AbstractException
{
    protected $code = 4503;
}
