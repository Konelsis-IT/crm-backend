<?php

declare(strict_types=1);

namespace App\Exceptions\Project;

use App\Exceptions\AbstractException;

/**
 * WBS-CBS dagilim yuzdeleri toplami 100'u asiyor.
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class AllocationExceededException extends AbstractException
{
    protected $code = 4098;
}
