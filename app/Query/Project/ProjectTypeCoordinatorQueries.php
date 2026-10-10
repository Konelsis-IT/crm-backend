<?php

declare(strict_types=1);

namespace App\Query\Project;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Project\Project;
use App\Models\Project\ProjectScope;
use App\Models\Project\ProjectTypeCoordinator;
use App\Services\Platform\SchemaReadiness;
use App\Support\Acquisition\ScopeTypes;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Proje tipi koordinatorlerinin okuma sorgulari (B49, D-175). Gecerli atamalar
 * (en cok alti satir) istek boyunca bir kez okunur; personel kartlari, proje
 * sayfasi ve listeler ayni sonucu kullanir. Yazan servis flush() cagirir.
 */
final class ProjectTypeCoordinatorQueries
{
    /** @var array<string, ProjectTypeCoordinator>|null */
    private static ?array $current = null;

    public static function flush(): void
    {
        self::$current = null;
    }

    /**
     * Gecerli koordinatorler: tip degeri => atama (personeliyle).
     *
     * @return array<string, ProjectTypeCoordinator>
     */
    public function current(): array
    {
        if (self::$current !== null) {
            return self::$current;
        }

        if (! SchemaReadiness::hasBatch('B49')) {
            return self::$current = [];
        }

        $rows = [];

        foreach (ProjectTypeCoordinator::query()->current()->with('personnel')->orderBy('id')->get() as $row) {
            $rows[self::typeValue($row->getAttribute('scope_type'))] = $row;
        }

        return self::$current = $rows;
    }

    /** Tipin gecerli koordinatoru. */
    public function forType(ProjectScopeType|string $type): ?ProjectTypeCoordinator
    {
        return $this->current()[self::typeValue($type)] ?? null;
    }

    /**
     * Personelin koordinator oldugu tipler (katalog sirasinda).
     *
     * @return list<ProjectScopeType>
     */
    public function typesFor(int $personnelId): array
    {
        $types = [];

        foreach (ProjectScopeType::cases() as $type) {
            $row = $this->current()[$type->value] ?? null;

            if ($row !== null && (int) $row->getAttribute('personnel_id') === $personnelId) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Projenin tiplerinden koordinatoru olanlar (proje sayfasi, liste sutunu).
     *
     * @return list<array{type: ProjectScopeType, coordinator: ProjectTypeCoordinator}>
     */
    public function forProject(Project $project): array
    {
        $out = [];

        foreach ($project->scopes as $scope) {
            $type = $scope->getAttribute('scope_type');
            $row = $type instanceof ProjectScopeType ? ($this->current()[$type->value] ?? null) : null;

            if ($type instanceof ProjectScopeType && $row !== null) {
                $out[] = ['type' => $type, 'coordinator' => $row];
            }
        }

        return $out;
    }

    /**
     * Yonetim ekranindaki satirlar: her proje tipi bir satir (koordinatoru
     * olsun olmasin), tipin proje sayisiyla. Anahtar tip degeridir.
     *
     * @return array<string, array{type: ProjectScopeType, personnel_id: int|null, personnel_name: string|null, since: Carbon|null, project_count: int}>
     */
    public function overview(): array
    {
        $counts = SchemaReadiness::hasBatch('B48')
            ? ProjectScope::query()
                ->selectRaw('scope_type, COUNT(DISTINCT project_id) as aggregate')
                ->groupBy('scope_type')
                ->pluck('aggregate', 'scope_type')
                ->all()
            : [];
        $rows = [];

        // D-177: Otomasyon / Process yalniz B50 ve ozellik acikken atanabilir (kisit B50'de).
        foreach (ScopeTypes::selectable() as $type) {
            $row = $this->current()[$type->value] ?? null;
            $since = $row?->getAttribute('valid_from');

            $rows[$type->value] = [
                'type' => $type,
                'personnel_id' => $row !== null ? (int) $row->getAttribute('personnel_id') : null,
                'personnel_name' => $row?->personnel?->full_name,
                'since' => $since instanceof Carbon ? $since : null,
                'project_count' => (int) ($counts[$type->value] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Su an koordinator olan personel (proje listesi suzgeci).
     *
     * @return array<int, string>
     */
    public function coordinatorOptions(): array
    {
        $options = [];

        foreach ($this->current() as $row) {
            $personnel = $row->personnel;

            if ($personnel !== null) {
                $options[(int) $personnel->getKey()] = (string) $personnel->full_name;
            }
        }

        asort($options);

        return $options;
    }

    /** Proje listesi suzgeci: secilen kisinin koordine ettigi tiplerden birini tasiyan projeler. */
    public function applyCoordinatorFilter(Builder $projects, int $personnelId): Builder
    {
        $types = array_map(static fn (ProjectScopeType $type): string => $type->value, $this->typesFor($personnelId));

        if ($types === []) {
            return $projects->whereRaw('1 = 0');
        }

        return $projects->whereHas('scopes', fn (Builder $scopes): Builder => $scopes->whereIn('scope_type', $types));
    }

    private static function typeValue(mixed $type): string
    {
        return $type instanceof BackedEnum ? (string) $type->value : (string) $type;
    }
}
