<?php

declare(strict_types=1);

namespace App\Exceptions\WorkRequest;

use App\Exceptions\AbstractException;

/** Talep eden kendi talebini kabul edemez, tamamlayamaz, reddedemez (D-84 netlestirmesi, 12 Eylul 2026). */
final class RequesterCannotHandleException extends AbstractException
{
    protected $code = 4507;
}
