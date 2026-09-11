<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\TenderVersionStatus;
use App\Models\Acquisition\TenderDeadline;
use App\Models\Acquisition\TenderNotice;
use App\Models\Acquisition\TenderRequirement;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Document\DocumentRevision;
use App\Models\Personnel\Personnel;
use App\Policies\TenderNoticeVersionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('tender_notice_versions')]
#[Fillable([
    'tender_notice_id', 'version_no', 'published_on', 'source_hash', 'source_document_revision_id', 'summary',
    'captured_by_personnel_id', 'status',
])]
#[UsePolicy(TenderNoticeVersionPolicy::class)]
class TenderNoticeVersion extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'published_on' => 'date',
            'status' => TenderVersionStatus::class,
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function notice(): BelongsTo
    {
        return $this->belongsTo(TenderNotice::class, 'tender_notice_id');
    }

    public function sourceDocumentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'source_document_revision_id');
    }

    public function capturer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'captured_by_personnel_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(TenderRequirement::class, 'tender_notice_version_id');
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(TenderDeadline::class, 'tender_notice_version_id');
    }
}
