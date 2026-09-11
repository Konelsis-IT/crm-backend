<?php

declare(strict_types=1);

namespace App\Exceptions\Chat;

use App\Exceptions\AbstractException;

/** Sohbet arsivlenmis ya da kilitli; yeni mesaj yazilamaz. */
final class ConversationLockedException extends AbstractException
{
    protected $code = 4403;
}
