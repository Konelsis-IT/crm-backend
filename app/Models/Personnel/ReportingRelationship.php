<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Personnel\ReportingRelationType;
use App\Enums\Personnel\ReportingScopeType;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\ReportingRelationshipPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kimin kime raporladiginin gecmisi (tarihceli).
 *
 * Ekranda ayri bir form yoktur; PersonnelService "Dogrudan Amir" secimini
 * (relation_type=line, scope_type=all) buraya otomatik yazar/kapatir.
 */
#[Table('reporting_relationships')]
#[Fillable(['personnel_id', 'manager_personnel_id', 'relation_type', 'scope_type', 'scope_id', 'valid_from', 'valid_until'])]
#[UsePolicy(ReportingRelationshipPolicy::class)]
class ReportingRelationship extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relation_type' => ReportingRelationType::class,
            'scope_type' => ReportingScopeType::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /** Bu tabloda updated_at/row_version yok; kapanan satir yalniz valid_until alir. */
    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'manager_personnel_id');
    }
}
