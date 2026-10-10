<?php

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Personnel\Personnel;
use App\Policies\ProjectTypeCoordinatorPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proje tipi koordinatoru (B49, D-175): proje mudurlerinden bagimsiz olarak
 * bir proje tipinin (GES, RES, TM, HES, BESS, ENH/EIH) butun projeleriyle
 * ilgilenen personel. Satir bir atamadir; valid_until bos olan satir gecerli
 * koordinatordur (tip basina en cok bir tane, uk_project_type_coordinators_open).
 * Degisiklikte eski satir kapanir, gecmis kalir. Yazma yalniz
 * ProjectTypeCoordinatorService ile.
 */
#[Table('project_type_coordinators')]
#[Fillable(['scope_type', 'personnel_id', 'valid_from', 'valid_until'])]
#[UsePolicy(ProjectTypeCoordinatorPolicy::class)]
class ProjectTypeCoordinator extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => ProjectScopeType::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'personnel_id');
    }

    /** Gecerli (kapanmamis) atamalar. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('valid_until');
    }
}
