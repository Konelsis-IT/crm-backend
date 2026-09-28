<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Yillik kod sayaci (B40, D-132): on ek (POTIS / TKLF / PRJ) + yil basina son
 * verilen numara. Yalniz YearlyCodeAllocator yazar.
 */
#[Table('business_code_sequences')]
#[Fillable(['code_prefix', 'code_year', 'last_number'])]
class BusinessCodeSequence extends Model
{
    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code_year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
