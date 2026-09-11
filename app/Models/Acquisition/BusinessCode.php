<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\BusinessCodeKind;
use App\Enums\Acquisition\BusinessCodeStatus;
use App\Models\Acquisition\BusinessCase;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\BusinessCodePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('business_codes')]
#[Fillable([
    'business_case_id', 'sequence_no', 'code_kind', 'issued_at', 'issued_by_personnel_id', 'predecessor_code_id',
    'status',
])]
#[UsePolicy(BusinessCodePolicy::class)]
class BusinessCode extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer',
            'code_kind' => BusinessCodeKind::class,
            'issued_at' => 'datetime',
            'status' => BusinessCodeStatus::class,
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function businessCase(): BelongsTo
    {
        return $this->belongsTo(BusinessCase::class, 'business_case_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'issued_by_personnel_id');
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_code_id');
    }
}
