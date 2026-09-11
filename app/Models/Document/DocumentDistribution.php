<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\DistributionKind;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\DocumentDistributionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir revizyonun bir aliciya dagitim kaydi.
 */
#[Table('document_distributions')]
#[Fillable([
    'document_revision_id', 'recipient_personnel_id', 'distribution_kind',
    'requires_acknowledgement', 'distributed_by_personnel_id', 'distributed_at', 'transmittal_id',
])]
#[UsePolicy(DocumentDistributionPolicy::class)]
class DocumentDistribution extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distribution_kind' => DistributionKind::class,
            'requires_acknowledgement' => 'boolean',
            'distributed_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'recipient_personnel_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'distributed_by_personnel_id');
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class, 'transmittal_id');
    }
}
