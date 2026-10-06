<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\Document;
use App\Models\Document\DocumentRevision;
use App\Policies\ProposalVersionScopePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teklif surumunun proje kapsami (B43, D-155): kapsam potansiyel isten teklife
 * tasindi. Tip basina miktar (GES MWp, BESS MWe / MWh, ENH km), birim maliyet /
 * satis, toplam maliyet / satis, RES kalemleri ve kapsam listesi belgesinin bu
 * surumun baktigi revizyonu. Ayni surumde bir tip yalniz bir kez bulunur.
 */
#[Table('proposal_version_scopes')]
#[Fillable([
    'proposal_version_id', 'scope_type', 'capacity_mwp', 'power_mwe', 'energy_mwh', 'length_km', 'unit_cost',
    'unit_sales', 'total_cost', 'total_sales', 'res_material_amount', 'res_construction_amount',
    'res_assembly_amount', 'scope_document_id', 'scope_document_revision_id', 'note',
])]
#[UsePolicy(ProposalVersionScopePolicy::class)]
class ProposalVersionScope extends Model
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
            'power_mwe' => 'decimal:3',
            'energy_mwh' => 'decimal:3',
            'length_km' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'unit_sales' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'res_material_amount' => 'decimal:2',
            'res_construction_amount' => 'decimal:2',
            'res_assembly_amount' => 'decimal:2',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    public function scopeDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'scope_document_id');
    }

    public function scopeDocumentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'scope_document_revision_id');
    }
}
