<?php

declare(strict_types=1);

namespace App\Exceptions\AiService;

use App\Exceptions\AbstractException;

/** The agent's daily call limit is used up (429) (D-192). */
final class AiServiceLimitReachedException extends AbstractException {}
