<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ProposalScopeDocumentRole;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teklif kapsamina bagli belge revizyonu (B51, D-181): bugun Maliyet listesi.
 * Her surum kendi kapsam satiri uzerinden baktigi revizyonu saklar; yeni surum
 * ayni revizyonu yeni satirla baglar (belge kopyalanmaz, D-158). Satir
 * degismez; kapsam kaldirilinca servis siler.
 */
#[Table('proposal_version_scope_documents')]
#[Fillable(['proposal_version_scope_id', 'document_revision_id', 'document_role', 'sort_order'])]
class ProposalVersionScopeDocument extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_role' => ProposalScopeDocumentRole::class,
            'sort_order' => 'integer',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function versionScope(): BelongsTo
    {
        return $this->belongsTo(ProposalVersionScope::class, 'proposal_version_scope_id');
    }

    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }
}
