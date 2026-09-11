<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\DependencyType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Project\WorkPackage;
use App\Policies\WorkPackageDependencyPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('work_package_dependencies')]
#[Fillable(['predecessor_package_id', 'successor_package_id', 'dependency_type', 'is_hard'])]
#[UsePolicy(WorkPackageDependencyPolicy::class)]
class WorkPackageDependency extends Model
{
    use HasAuditColumns;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dependency_type' => DependencyType::class,
            'is_hard' => 'boolean',
        ];
    }

    public function tracksUpdateAudit(): bool
    {
        return false;
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class, 'predecessor_package_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class, 'successor_package_id');
    }
}
