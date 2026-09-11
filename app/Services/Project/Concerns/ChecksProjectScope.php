<?php

declare(strict_types=1);

namespace App\Services\Project\Concerns;

use App\Exceptions\Project\SameProjectRequiredException;
use Illuminate\Database\Eloquent\Model;

/**
 * "Ayni proje" garantisi (11 giris notu, 13 SS2): bir cocuk kaydin
 * bagladigi her kayit ayni project_id'yi tasimalidir. DB composite FK'lar
 * ikinci savunma hattidir; burada anlasilir bir is hatasi verilir.
 */
trait ChecksProjectScope
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function assertSameProject(int $projectId, string $modelClass, int|string|null $key, string $column = 'project_id'): void
    {
        if ($key === null || $key === '') {
            return;
        }

        $owner = $modelClass::query()->whereKey($key)->value($column);

        if ($owner === null || (int) $owner !== $projectId) {
            throw SameProjectRequiredException::make();
        }
    }
}
