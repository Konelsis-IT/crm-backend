<?php

declare(strict_types=1);

namespace App\Exceptions\Party;

use App\Exceptions\AbstractException;

/** Alt faaliyet alani yalniz bir ana faaliyet alaninin altina baglanabilir (iki seviye). */
final class ActivityAreaParentInvalidException extends AbstractException
{
    protected $code = 4801;
}
