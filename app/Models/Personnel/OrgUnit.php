<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Enums\Personnel\OrgUnitStatus;
use App\Enums\Personnel\OrgUnitType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Reference\LegalEntity;
use App\Policies\OrgUnitPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organizasyon birimi (sirket, bolum, departman, ofis, saha, komite).
 *
 * Ust birim iliskisi org_unit_relations'ta tarihcelidir; bu modelde
 * "guncel ust birim" currentParent() ile okunur. Fonksiyonel yoneticilik
 * canonical tasarimda reporting_relationships'tedir, ama sadelik icin
 * (D-62) burada denormalize manager_personnel_id kolonu tutulur.
 */
#[Table('org_units')]
#[Fillable(['legal_entity_id', 'code', 'name', 'unit_type', 'cost_center_code', 'manager_personnel_id', 'status', 'valid_from', 'valid_until'])]
#[UsePolicy(OrgUnitPolicy::class)]
class OrgUnit extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_type' => OrgUnitType::class,
            'status' => OrgUnitStatus::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'legal_entity_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'manager_personnel_id');
    }

    public function personnel(): HasMany
    {
        return $this->hasMany(Personnel::class, 'org_unit_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'org_unit_id');
    }

    /** Bu birimin ust birim gecmisi (tarihceli, salt okunur). */
    public function parentRelations(): HasMany
    {
        return $this->hasMany(OrgUnitRelation::class, 'child_org_unit_id');
    }

    /** Su an gecerli ust birim, varsa. */
    public function currentParent(): ?self
    {
        $relation = $this->parentRelations()
            ->where('relation_type', 'hierarchy')
            ->whereNull('valid_until')
            ->first();

        return $relation?->parent;
    }
}
