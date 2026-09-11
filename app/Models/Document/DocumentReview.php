<?php

declare(strict_types=1);

namespace App\Models\Document;

use App\Enums\Document\DocumentReviewDecision;
use App\Enums\Document\DocumentReviewType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\DocumentReviewPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir revizyon icin kontrol/onay/kalite kararı. Onay motoru (M03 B07)
 * gelene kadar elle kaydedilir; approval_request_id bu batch'te yoktur.
 */
#[Table('document_reviews')]
#[Fillable(['document_revision_id', 'reviewer_personnel_id', 'review_type', 'decision', 'comment', 'decided_at', 'approval_request_id'])]
#[UsePolicy(DocumentReviewPolicy::class)]
class DocumentReview extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review_type' => DocumentReviewType::class,
            'decision' => DocumentReviewDecision::class,
            'decided_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'reviewer_personnel_id');
    }
}
