<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Models\Concerns\HasAuditColumns;
use App\Policies\PersonnelAssignmentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personelin organizasyon birimi/gorev gecmisi.
 *
 * Yalniz PersonnelService tarafindan yazilir; ekranda ayri bir
 * olusturma/duzenleme formu yoktur, personelin kartinin altinda salt
 * okunur bir liste olarak gorunur. effective_to NULL olan satir o an
 * gecerli atamadir. Dogrudan amir gecmisi burada degil
 * ReportingRelationship'te tutulur (D-62).
 */
#[Table('personnel_assignments')]
#[Fillable(['personnel_id', 'org_unit_id', 'job_title', 'effective_from', 'effective_to', 'note'])]
#[UsePolicy(PersonnelAssignmentPolicy::class)]
class PersonnelAssignment extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /** Bu tabloda updated_at/row_version yok; kapanan satir yalniz effective_to alir. */
    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }
}
