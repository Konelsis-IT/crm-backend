<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Acil onay icin plan tarihi yok ya da pencere disinda (:days). */
final class UrgentNotAllowedException extends AbstractException
{
    protected $code = 4703;
}
