<?php

declare(strict_types=1);

namespace App\Exceptions\Project;

use App\Exceptions\AbstractException;

/**
 * Bagimlilik zinciri dongu olusturuyor.
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class DependencyCycleException extends AbstractException
{
    protected $code = 4096;
}
