<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\PartyActivityAreaPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarafin faaliyet satiri (B33, D-107): Proje tipi + Faaliyet alani + Alt
 * faaliyet alani. Proje tipi bos ise "tum proje tipleri"dir.
 */
#[Table('party_activity_areas')]
#[Fillable(['party_id', 'project_type', 'activity_area_id', 'sub_activity_area_id'])]
#[UsePolicy(PartyActivityAreaPolicy::class)]
class PartyActivityArea extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_type' => ProjectScopeType::class,
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function activityArea(): BelongsTo
    {
        return $this->belongsTo(ActivityArea::class, 'activity_area_id');
    }

    public function subActivityArea(): BelongsTo
    {
        return $this->belongsTo(ActivityArea::class, 'sub_activity_area_id');
    }

    /** Okunur ozet: "HES · Ekipman tedariki › Turbin"; proje tipi yoksa "Tum proje tipleri". */
    public function summary(): string
    {
        $type = $this->project_type instanceof ProjectScopeType
            ? $this->project_type->getLabel()
            : __('party.values.all_project_types');

        $area = $this->activityArea?->name_tr ?? '-';
        $sub = $this->subActivityArea?->name_tr;

        return $type.' · '.$area.($sub !== null ? ' › '.$sub : '');
    }
}
