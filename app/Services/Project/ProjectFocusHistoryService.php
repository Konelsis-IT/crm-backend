<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Services\AbstractService;

/**
 * ProjectFocusHistory servisi.
 *
 * Ekranda ayri bir kural yoktur; bes temel islem AbstractService'ten gelir.
 */
final class ProjectFocusHistoryService extends AbstractService
{
    protected string $model = \App\Models\Project\ProjectFocusHistory::class;
}
