<?php

declare(strict_types=1);

namespace App\Exceptions\Notification;

use App\Exceptions\AbstractException;

/** Gonderen bu hedef kitleye bildirim gonderme iznine sahip degil. */
final class NotificationScopeNotAllowedException extends AbstractException
{
    protected $code = 4301;
}
