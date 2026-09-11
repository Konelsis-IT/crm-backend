<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\TenderComplianceState;
use App\Enums\Acquisition\TenderRequirementType;
use App\Models\Acquisition\TenderNoticeVersion;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\TenderRequirementPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('tender_requirements')]
#[Fillable([
    'tender_notice_version_id', 'requirement_code', 'requirement_type', 'description', 'is_mandatory',
    'compliance_state', 'evaluated_by_personnel_id', 'sort_order',
])]
#[UsePolicy(TenderRequirementPolicy::class)]
class TenderRequirement extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requirement_type' => TenderRequirementType::class,
            'is_mandatory' => 'boolean',
            'compliance_state' => TenderComplianceState::class,
            'sort_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(TenderNoticeVersion::class, 'tender_notice_version_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'evaluated_by_personnel_id');
    }
}
