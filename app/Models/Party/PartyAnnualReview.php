<?php

declare(strict_types=1);

namespace App\Models\Party;

use App\Enums\Party\AnnualReviewOutcome;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Models\Personnel\Personnel;
use App\Policies\PartyAnnualReviewPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('party_annual_reviews')]
#[Fillable([
    'party_id', 'review_year', 'reviewer_employee_id', 'outcome', 'score', 'summary', 'reviewed_at',
    'next_review_on',
])]
#[UsePolicy(PartyAnnualReviewPolicy::class)]
class PartyAnnualReview extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'review_year' => 'integer',
            'outcome' => AnnualReviewOutcome::class,
            'score' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'next_review_on' => 'date',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'reviewer_employee_id');
    }
}
