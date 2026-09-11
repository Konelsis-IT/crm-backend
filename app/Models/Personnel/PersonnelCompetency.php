<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Personnel\CompetencyLevel;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personelin sahip oldugu yetkinlik ve seviyesi.
 */
#[Table('personnel_competencies')]
#[Fillable(['personnel_id', 'competency_id', 'level', 'note'])]
class PersonnelCompetency extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => CompetencyLevel::class,
        ];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class, 'competency_id');
    }
}
