<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Personnel\TrainingAttendanceOutcome;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\TrainingAttendancePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir personelin bir egitime katilim kaydi.
 */
#[Table('training_attendances')]
#[Fillable(['training_id', 'personnel_id', 'attended_on', 'outcome', 'score'])]
#[UsePolicy(TrainingAttendancePolicy::class)]
class TrainingAttendance extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => TrainingAttendanceOutcome::class,
            'attended_on' => 'date',
            'score' => 'decimal:2',
        ];
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class, 'training_id');
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }
}
