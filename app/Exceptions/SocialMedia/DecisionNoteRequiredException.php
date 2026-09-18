<?php

declare(strict_types=1);

namespace App\Exceptions\SocialMedia;

use App\Exceptions\AbstractException;

/** Ret ve revize kararinda aciklama zorunlu. */
final class DecisionNoteRequiredException extends AbstractException
{
    protected $code = 4701;
}
