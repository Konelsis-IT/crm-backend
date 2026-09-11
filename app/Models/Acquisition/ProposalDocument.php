<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ProposalDocumentRole;
use App\Models\Acquisition\ProposalVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Policies\ProposalDocumentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('proposal_documents')]
#[Fillable(['proposal_version_id', 'document_revision_id', 'document_role', 'sort_order'])]
#[UsePolicy(ProposalDocumentPolicy::class)]
class ProposalDocument extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_role' => ProposalDocumentRole::class,
            'sort_order' => 'integer',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }
}
