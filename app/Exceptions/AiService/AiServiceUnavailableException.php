<?php

declare(strict_types=1);

namespace App\Exceptions\AiService;

use App\Exceptions\AbstractException;

/** Konelsis AI Service could not be reached or answered with a server error (D-192). */
final class AiServiceUnavailableException extends AbstractException {}
