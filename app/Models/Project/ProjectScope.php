<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Acquisition\ProposalVersionScope;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\ProjectScopePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Projenin kendi kapsam satiri / proje tipi (B48, D-174). Tip katalogu
 * potansiyel is ve teklifle ortaktir (ProjectScopeType: ayni ad, simge,
 * renk); isterler ayridir: proje olculeri (MWp, MW, MWe / MWh, km, adet),
 * sozlesme tutari ve butce. Tekliften donusen projede satir kabul edilen
 * teklif surumunun kapsamindan kopyalanir (source_proposal_version_scope_id).
 * Ayni projede bir tip yalniz bir kez bulunur.
 */
#[Table('project_scopes')]
#[Fillable([
    'project_id', 'scope_type', 'capacity_mwp', 'capacity_mw', 'power_mwe', 'energy_mwh', 'length_km', 'unit_count',
    'contract_amount', 'budget_amount', 'source_proposal_version_scope_id', 'note',
])]
#[UsePolicy(ProjectScopePolicy::class)]
class ProjectScope extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => ProjectScopeType::class,
            'capacity_mwp' => 'decimal:3',
            'capacity_mw' => 'decimal:3',
            'power_mwe' => 'decimal:3',
            'energy_mwh' => 'decimal:3',
            'length_km' => 'decimal:3',
            'unit_count' => 'integer',
            'contract_amount' => 'decimal:2',
            'budget_amount' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** Kopyalandigi teklif kapsami satiri (tekliften donusumde). */
    public function sourceProposalVersionScope(): BelongsTo
    {
        return $this->belongsTo(ProposalVersionScope::class, 'source_proposal_version_scope_id');
    }
}
