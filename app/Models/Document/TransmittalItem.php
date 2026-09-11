<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Models\Concerns\HasAuditColumns;
use App\Policies\TransmittalItemPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir teslim tutanagina eklenen tek bir doküman revizyonu.
 */
#[Table('transmittal_items')]
#[Fillable(['transmittal_id', 'document_revision_id', 'sort_order', 'copies'])]
#[UsePolicy(TransmittalItemPolicy::class)]
class TransmittalItem extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'copies' => 'integer',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function transmittal(): BelongsTo
    {
        return $this->belongsTo(Transmittal::class, 'transmittal_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }
}
