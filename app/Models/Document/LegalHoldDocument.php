<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\LegalHoldDocumentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir hukuki tutmaya eklenen doküman (veya belirli bir revizyon;
 * revizyon NULL ise tum revizyonlar kapsam dahilindedir).
 */
#[Table('legal_hold_documents')]
#[Fillable(['legal_hold_id', 'document_id', 'document_revision_id', 'added_by_personnel_id', 'added_at'])]
#[UsePolicy(LegalHoldDocumentPolicy::class)]
class LegalHoldDocument extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function legalHold(): BelongsTo
    {
        return $this->belongsTo(LegalHold::class, 'legal_hold_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }

    public function adder(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'added_by_personnel_id');
    }
}
