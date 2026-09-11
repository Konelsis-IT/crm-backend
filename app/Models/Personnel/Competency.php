<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Personnel\CompetencyCategory;
use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\CompetencyPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Yetkinlik tanimi (ornegin SCADA, yuksek gerilim, ISG).
 */
#[Table('competencies')]
#[Fillable(['code', 'name', 'category', 'description', 'status'])]
#[UsePolicy(CompetencyPolicy::class)]
class Competency extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CompetencyCategory::class,
            'status' => ActiveStatus::class,
        ];
    }

    public function personnel(): BelongsToMany
    {
        return $this->belongsToMany(Personnel::class, 'personnel_competencies', 'competency_id', 'personnel_id')
            ->withPivot(['level', 'note']);
    }
}
