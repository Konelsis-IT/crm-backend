<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Reference\AllocationPurpose;
use App\Enums\Reference\AllocationStatus;
use App\Models\Concerns\AppendOnly;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TKLF-n / PRJ-n kodlari icin tek ve geri kullanilmayan global sira.
 * Birincil anahtarin kendisi sira numarasidir.
 */
#[Table('business_number_allocations')]
#[Fillable(['purpose', 'status', 'allocated_at', 'allocated_by_personnel_id'])]
class BusinessNumberAllocation extends Model
{
    use AppendOnly;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => AllocationPurpose::class,
            'status' => AllocationStatus::class,
            'allocated_at' => 'datetime',
        ];
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'allocated_by_personnel_id');
    }
}
