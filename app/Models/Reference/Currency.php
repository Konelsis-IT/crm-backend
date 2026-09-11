<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ISO 4217 currency master data; natural primary key `code`.
 */
#[Table('currencies')]
#[Fillable(['code', 'name_tr', 'name_en', 'decimal_places', 'status'])]
class Currency extends Model
{
    use HasAuditColumns;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'status' => ActiveStatus::class,
        ];
    }
}
