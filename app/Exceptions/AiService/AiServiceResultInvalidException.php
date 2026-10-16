<?php

declare(strict_types=1);

namespace App\Exceptions\AiService;

use App\Exceptions\AbstractException;

/** The service answered with a body that does not match the contract (D-192). */
final class AiServiceResultInvalidException extends AbstractException {}
