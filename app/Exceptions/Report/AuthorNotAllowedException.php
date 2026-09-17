<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Bu taslagi yazma yetkisi yok. */
final class AuthorNotAllowedException extends AbstractException
{
    protected $code = 4603;
}
