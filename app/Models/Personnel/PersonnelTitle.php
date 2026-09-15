<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unvan katalogu (B26, D-88): departmandan bagimsiz, sirket genelinde
 * tekrar eden kademe adi (orn. "Sorumlu", "Mudur", "Grup Muduru").
 * "Gorev" (Position/job_title) ile karistirilmamalidir; gorev departmana
 * baglidir, unvan degildir.
 */
#[Table('personnel_titles')]
#[Fillable(['code', 'name', 'rank_level', 'status'])]
class PersonnelTitle extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank_level' => 'integer',
            'status' => ActiveStatus::class,
        ];
    }

    public function personnel(): HasMany
    {
        return $this->hasMany(Personnel::class, 'title_id');
    }
}
