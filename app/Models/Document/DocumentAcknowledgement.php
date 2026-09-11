<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\AcknowledgementKind;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\DocumentAcknowledgementPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir personelin bir revizyonu okudugunu/kabul ettigini/egitim aldigini
 * teyit kaydi.
 */
#[Table('document_acknowledgements')]
#[Fillable(['document_revision_id', 'personnel_id', 'acknowledgement_kind', 'acknowledged_at', 'comment'])]
#[UsePolicy(DocumentAcknowledgementPolicy::class)]
class DocumentAcknowledgement extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acknowledgement_kind' => AcknowledgementKind::class,
            'acknowledged_at' => 'datetime',
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

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }
}
