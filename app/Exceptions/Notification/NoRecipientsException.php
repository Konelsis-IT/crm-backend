<?php

declare(strict_types=1);

namespace App\Exceptions\Notification;

use App\Exceptions\AbstractException;

/** Secilen hedef kitlede (gonderen disinda) aktif alici yok. */
final class NoRecipientsException extends AbstractException
{
    protected $code = 4302;
}
