<?php

declare(strict_types=1);

namespace App\Models\Acquisition;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Concerns\HasAuditColumns;
use App\Policies\ProjectReferencePolicy;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sirketin referansi (B50, D-177): yapilmis bir isin tek satirlik metni
 * (Excel'deki "GES REFERANSLARIMIZ" listelerinin satiri). Bir referans
 * birden fazla proje tipine baglanabilir (scopeTypes); teklifteki referans
 * listesi teklifin tiplerine gore suzulur. Silinmez, arsive alinir (yalniz
 * archived_at, D-156); sira `sort_order`.
 */
#[Table('project_references')]
#[Fillable(['title', 'sort_order'])]
#[UsePolicy(ProjectReferencePolicy::class)]
class ProjectReference extends Model
{
    use HasAuditColumns;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function scopeTypes(): HasMany
    {
        return $this->hasMany(ProjectReferenceScopeType::class, 'project_reference_id')->orderBy('id');
    }

    public function isArchived(): bool
    {
        return $this->getAttribute('archived_at') !== null;
    }

    /** Arsivdekiler disarida (B50 yoksa tablo da yok; sorgu bos doner). */
    public function scopeNotArchived(Builder $query): Builder
    {
        return SchemaReadiness::hasBatch('B50') ? $query->whereNull($query->qualifyColumn('archived_at')) : $query;
    }

    /**
     * Referansin proje tipleri (katalog sirasiyla).
     *
     * @return list<ProjectScopeType>
     */
    public function types(): array
    {
        $values = $this->scopeTypes->map(static function (ProjectReferenceScopeType $row): string {
            $type = $row->getAttribute('scope_type');

            return $type instanceof BackedEnum ? (string) $type->value : (string) $type;
        })->all();

        return array_values(array_filter(
            ProjectScopeType::cases(),
            static fn (ProjectScopeType $type): bool => in_array($type->value, $values, true),
        ));
    }
}
