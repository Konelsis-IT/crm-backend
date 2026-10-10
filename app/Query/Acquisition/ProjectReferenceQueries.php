<?php

declare(strict_types=1);

namespace App\Query\Acquisition;

use App\Models\Acquisition\ProjectReference;
use App\Models\Acquisition\ProjectReferenceScopeType;
use App\Services\Platform\SchemaReadiness;
use App\Support\Acquisition\ScopeTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Referans okumalari (B50, D-177): liste ve pencere tablolarinin sorgusu,
 * arsiv suzgeci, proje tipi suzgeci, tip basina sayilar ve Excel satirlari.
 * Sira her yerde `sort_order`, sonra kimlik (Excel'deki sira).
 */
final class ProjectReferenceQueries
{
    /** Tablolarin temel sorgusu (tipleriyle, Excel sirasinda). */
    public function tableQuery(): Builder
    {
        return $this->ordered(ProjectReference::query());
    }

    /** Tipleri yuklu, Excel sirasinda. */
    public function ordered(Builder $query): Builder
    {
        return $query
            ->with('scopeTypes')
            ->orderBy($query->qualifyColumn('sort_order'))
            ->orderBy($query->qualifyColumn('id'));
    }

    /** Arsiv suzgeci (D-156): active (varsayilan) / archived / all. */
    public function archiveScope(Builder $query, string $mode): Builder
    {
        return match ($mode) {
            'archived' => $query->whereNotNull($query->qualifyColumn('archived_at')),
            'all' => $query,
            default => $query->whereNull($query->qualifyColumn('archived_at')),
        };
    }

    /**
     * Verilen tiplerden en az birine bagli referanslar; liste bossa sorgu degismez.
     *
     * @param  iterable<mixed>  $types
     */
    public function withTypes(Builder $query, iterable $types): Builder
    {
        $values = ScopeTypes::values($types);

        if ($values === []) {
            return $query;
        }

        return $query->whereHas('scopeTypes', static fn (Builder $rows): Builder => $rows->whereIn('scope_type', $values));
    }

    /**
     * Tip basina arsivde olmayan referans sayisi (yalniz istenen tipler, sirali).
     *
     * @param  iterable<mixed>  $types
     * @return array<string, int>
     */
    public function countsByType(iterable $types): array
    {
        $values = ScopeTypes::values($types);

        if ($values === [] || ! SchemaReadiness::hasBatch('B50')) {
            return [];
        }

        $counts = ProjectReferenceScopeType::query()
            ->whereIn('scope_type', $values)
            ->whereHas('reference', static fn (Builder $reference): Builder => $reference->whereNull('archived_at'))
            ->selectRaw('scope_type, COUNT(*) AS total')
            ->groupBy('scope_type')
            ->toBase()
            ->pluck('total', 'scope_type')
            ->all();

        $ordered = [];

        foreach ($values as $value) {
            $ordered[$value] = (int) ($counts[$value] ?? 0);
        }

        return $ordered;
    }

    /** D-183: arsivde olmayan referanslarin toplami (Referanslar ekraninin "Tumu" sekmesi). */
    public function activeTotal(): int
    {
        if (! SchemaReadiness::hasBatch('B50')) {
            return 0;
        }

        return ProjectReference::query()->whereNull('archived_at')->count();
    }

    /**
     * Excel satirlari: arsivde olmayan, verilen tiplere bagli referanslar (bos liste = hepsi).
     *
     * @param  iterable<mixed>  $types
     * @return Collection<int, ProjectReference>
     */
    public function forExport(iterable $types): Collection
    {
        if (! SchemaReadiness::hasBatch('B50')) {
            return new Collection;
        }

        return $this->withTypes($this->archiveScope($this->tableQuery(), 'active'), $types)->get();
    }

    /** Yeni referansin sirasi: listenin sonu. */
    public function nextSortOrder(): int
    {
        return (int) ProjectReference::query()->max('sort_order') + 1;
    }
}
