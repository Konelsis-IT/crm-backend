<?php

declare(strict_types=1);

namespace App\Exceptions\Project;

use App\Exceptions\AbstractException;

/**
 * Ayni bilesen tanimi projeye ikinci kez ekleniyor
 * (uk_project_components_project_component).
 *
 * Mesaj dil dosyasindaki exceptions anahtarindan gelir.
 */
final class ProjectComponentDuplicateException extends AbstractException
{
    protected $code = 4211;
}
