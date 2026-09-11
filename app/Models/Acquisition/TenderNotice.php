<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\TenderNoticeStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\TenderNoticeVersion;
use App\Models\Acquisition\TenderSource;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Party\Party;
use App\Policies\TenderNoticePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('tender_notices')]
#[Fillable([
    'business_case_id', 'tender_source_id', 'external_notice_id', 'title', 'issuer_party_id', 'notice_url',
    'captured_at', 'current_version_id', 'status',
])]
#[UsePolicy(TenderNoticePolicy::class)]
class TenderNotice extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'status' => TenderNoticeStatus::class,
        ];
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(TenderSource::class, 'tender_source_id');
    }

    public function issuerParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'issuer_party_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(TenderNoticeVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TenderNoticeVersion::class, 'tender_notice_id');
    }
}
