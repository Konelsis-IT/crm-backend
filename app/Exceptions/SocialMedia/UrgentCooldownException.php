<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Acil onay kisa sure once istendi; bekleme suresi dolmadi (:hours). */
final class UrgentCooldownException extends AbstractException
{
    protected $code = 4704;
}
