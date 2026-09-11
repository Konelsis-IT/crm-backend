<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\DocumentLinkRole;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\DocumentLinkPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir dokumani baska bir kayda (registry ile sinirli target_type) baglar.
 */
#[Table('document_links')]
#[Fillable(['document_id', 'document_revision_id', 'target_type', 'target_id', 'link_role', 'linked_by_personnel_id'])]
#[UsePolicy(DocumentLinkPolicy::class)]
class DocumentLink extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'link_role' => DocumentLinkRole::class,
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'document_revision_id');
    }

    public function linker(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'linked_by_personnel_id');
    }
}
