<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ContractDocumentRole;
use App\Models\Acquisition\ContractVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Policies\ContractDocumentPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('contract_documents')]
#[Fillable(['contract_version_id', 'document_revision_id', 'document_role'])]
#[UsePolicy(ContractDocumentPolicy::class)]
class ContractDocument extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_role' => ContractDocumentRole::class,
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'contract_version_id');
    }

    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }
}
