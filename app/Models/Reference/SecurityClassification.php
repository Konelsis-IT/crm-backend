<?php

declare(strict_types=1);

namespace App\Models\Reference;

use App\Enums\Reference\ClassificationCode;
use App\Enums\Shared\ActiveStatus;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('security_classifications')]
#[Fillable(['code', 'rank', 'name_tr', 'name_en', 'description', 'external_analysis_allowed', 'status'])]
class SecurityClassification extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => ClassificationCode::class,
            'rank' => 'integer',
            'external_analysis_allowed' => 'boolean',
            'status' => ActiveStatus::class,
        ];
    }
}
