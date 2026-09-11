<?php

declare(strict_types=1);

namespace App\Services\Project\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Proje icindeki kayit numaralari (ISS-001, RSK-001 ...): proje basina
 * artan, bosluk kabul eden, benzersizligi DB unique'inin korudugu sira.
 */
trait NumbersProjectRecords
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function nextProjectNumber(string $modelClass, int $projectId, string $column, string $prefix): string
    {
        $count = $modelClass::query()->where('project_id', $projectId)->count();

        do {
            $count++;
            $candidate = sprintf('%s-%03d', $prefix, $count);
        } while ($modelClass::query()->where('project_id', $projectId)->where($column, $candidate)->exists());

        return $candidate;
    }
}
