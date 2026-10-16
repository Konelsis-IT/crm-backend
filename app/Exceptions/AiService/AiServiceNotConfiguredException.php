<?php

declare(strict_types=1);

namespace App\Exceptions\AiService;

use App\Exceptions\AbstractException;

/** AI_SERVICE_URL / client id / token / secret are missing or the URL is not allowed (D-192). */
final class AiServiceNotConfiguredException extends AbstractException {}
