<?php

declare(strict_types=1);

namespace App\Models\Activity;

use App\Enums\Activity\ReferenceUsageContext;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('reference_type_usages')]
#[Fillable(['reference_type_id', 'usage_context'])]
class ReferenceTypeUsage extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_context' => ReferenceUsageContext::class,
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function referenceType(): BelongsTo
    {
        return $this->belongsTo(ReferenceType::class, 'reference_type_id');
    }
}
