<?php

declare(strict_types=1);

namespace App\Exceptions\Chat;

use App\Exceptions\AbstractException;

/** Personel bu sohbetin uyesi degil. */
final class NotAMemberException extends AbstractException
{
    protected $code = 4401;
}
