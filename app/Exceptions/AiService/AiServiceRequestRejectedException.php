<?php

declare(strict_types=1);

namespace App\Exceptions\AiService;

use App\Exceptions\AbstractException;

/** The service rejected the request content (422, conflicts) (D-192). */
final class AiServiceRequestRejectedException extends AbstractException {}
