<?php

declare(strict_types=1);

namespace App\Exceptions\Party;

use App\Exceptions\AbstractException;

/** Bir gorusme notunun teklifleri ayni potansiyel ise ait olmalidir (D-137). */
final class MeetingNoteProposalMismatchException extends AbstractException
{
    protected $code = 4802;
}
