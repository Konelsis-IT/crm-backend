<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Reference\UomDimension;
use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('units_of_measure')]
#[Fillable(['dimension', 'code', 'symbol', 'name_tr', 'name_en', 'base_unit_id', 'to_base_factor', 'status'])]
class UnitOfMeasure extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dimension' => UomDimension::class,
            'to_base_factor' => 'decimal:12',
            'status' => ActiveStatus::class,
        ];
    }


    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }
}
