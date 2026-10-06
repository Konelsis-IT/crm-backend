<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ChecklistAnswer;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\BusinessCaseChecklistAnswerPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teklif oncesi kontrol listesinin bir alt maddesine verilen cevap (B43,
 * D-155). Madde tanimi kodda (ChecklistTemplates); satir sablon + madde
 * koduyla tekildir.
 */
#[Table('business_case_checklist_answers')]
#[Fillable(['business_case_id', 'template_code', 'item_code', 'answer', 'note'])]
#[UsePolicy(BusinessCaseChecklistAnswerPolicy::class)]
class BusinessCaseChecklistAnswer extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer' => ChecklistAnswer::class,
        ];
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }
}
