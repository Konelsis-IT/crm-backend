<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\Document;
use App\Policies\BusinessCaseScopePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Is dosyasinda secilen proje kapsam tipi satiri (B29, D-101): tip basina
 * tutar alanlari ve Dokumanlar'da saklanan kapsam listesi (KPS) belgesi.
 * Ayni is dosyasinda bir tip yalniz bir kez bulunur.
 */
#[Table('business_case_scopes')]
#[Fillable([
    'business_case_id', 'scope_type', 'capacity_mw', 'cost_amount', 'sales_amount', 'cost_per_mw', 'sales_per_mw',
    'res_material_amount', 'res_construction_amount', 'res_assembly_amount', 'tm_total_cost', 'tm_total_sales',
    'tm_feeder_cost', 'hes_unit_cost', 'scope_document_id', 'note',
])]
#[UsePolicy(BusinessCaseScopePolicy::class)]
class BusinessCaseScope extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => ProjectScopeType::class,
            'capacity_mw' => 'decimal:3',
            'cost_amount' => 'decimal:2',
            'sales_amount' => 'decimal:2',
            'cost_per_mw' => 'decimal:2',
            'sales_per_mw' => 'decimal:2',
            'res_material_amount' => 'decimal:2',
            'res_construction_amount' => 'decimal:2',
            'res_assembly_amount' => 'decimal:2',
            'tm_total_cost' => 'decimal:2',
            'tm_total_sales' => 'decimal:2',
            'tm_feeder_cost' => 'decimal:2',
            'hes_unit_cost' => 'decimal:2',
        ];
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    /** Yuklenen kapsam listesi (Excel) belgesi; yeni yukleme yeni revizyon acar. */
    public function scopeDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'scope_document_id');
    }
}
