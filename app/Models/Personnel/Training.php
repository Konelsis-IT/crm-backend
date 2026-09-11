<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Personnel\TrainingKind;
use App\Enums\Personnel\TrainingStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\TrainingPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Egitim tanimi (bir egitim oturumu/programi).
 */
#[Table('trainings')]
#[Fillable(['code', 'name', 'training_kind', 'provider', 'planned_on', 'duration_hours', 'status'])]
#[UsePolicy(TrainingPolicy::class)]
class Training extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'training_kind' => TrainingKind::class,
            'status' => TrainingStatus::class,
            'planned_on' => 'date',
            'duration_hours' => 'decimal:2',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(TrainingAttendance::class, 'training_id');
    }
}
