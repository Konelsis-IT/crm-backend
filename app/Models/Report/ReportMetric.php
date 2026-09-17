<?php

declare(strict_types=1);

namespace App\Models\Report;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KPI projeksiyonu (B10A): taslagin metrik olarak isaretledigi sayisal
 * cevaplar gonderimde buraya yazilir; raporlar arasi karsilastirma ve
 * egilim okumalari bu tablodan yapilir. Satirlar gonderimde yeniden uretilir.
 */
#[Table('report_metrics')]
#[Fillable(['report_id', 'metric_code', 'metric_value', 'unit', 'projected_at'])]
class ReportMetric extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_value' => 'decimal:8',
            'projected_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }
}
