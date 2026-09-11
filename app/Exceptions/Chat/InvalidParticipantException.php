<?php

declare(strict_types=1);

namespace App\Exceptions\Chat;

use App\Exceptions\AbstractException;

/** Secilen kisi aktif degil ya da kisinin kendisi. */
final class InvalidParticipantException extends AbstractException
{
    protected $code = 4404;
}
