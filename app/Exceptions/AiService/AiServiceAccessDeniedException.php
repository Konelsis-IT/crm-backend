<?php

declare(strict_types=1);

namespace App\Exceptions\AiService;

use App\Exceptions\AbstractException;

/** The service refused the CRM credential, capability or data classification (401/403) (D-192). */
final class AiServiceAccessDeniedException extends AbstractException {}
