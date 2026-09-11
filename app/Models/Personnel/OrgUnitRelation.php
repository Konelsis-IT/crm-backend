<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Personnel\OrgUnitRelationType;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\OrgUnitRelationPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Organizasyon biriminin ust birim gecmisi (tarihceli).
 *
 * Ekranda ayri bir form yoktur; OrgUnitService "ust birim" secimini
 * buraya otomatik yazar/kapatir (personnel_assignments'taki desenin
 * aynisi).
 */
#[Table('org_unit_relations')]
#[Fillable(['parent_org_unit_id', 'child_org_unit_id', 'relation_type', 'valid_from', 'valid_until'])]
#[UsePolicy(OrgUnitRelationPolicy::class)]
class OrgUnitRelation extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relation_type' => OrgUnitRelationType::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /** Bu tabloda updated_at/row_version yok; kapanan satir yalniz valid_until alir. */
    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'parent_org_unit_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'child_org_unit_id');
    }
}
