<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Acil onay bildirimi icin ulasilabilir onayci yok; iz yazilmaz. */
final class UrgentNoRecipientException extends AbstractException
{
    protected $code = 4714;
}
