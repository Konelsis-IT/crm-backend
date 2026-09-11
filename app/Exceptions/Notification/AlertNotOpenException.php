<?php

declare(strict_types=1);

namespace App\Exceptions\Notification;

use App\Exceptions\AbstractException;

/** Uyari acik degil; gordum/kapat islemi uygulanamaz. */
final class AlertNotOpenException extends AbstractException
{
    protected $code = 4303;
}
