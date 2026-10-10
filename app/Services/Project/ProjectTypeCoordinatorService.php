<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Acquisition\ProjectScopeType;
use App\Exceptions\Project\CoordinatorNotActiveException;
use App\Models\Personnel\Personnel;
use App\Models\Project\ProjectTypeCoordinator;
use App\Query\Project\ProjectTypeCoordinatorQueries;
use App\Services\AbstractService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Proje tipi koordinatorleri (B49, D-175; 8 Ekim 2026 kullanici talimati:
 * "Proje mudurlerinden bagimsiz olarak proje tipinin tamamini koordine
 * edenler var ... Sisteme bir Proje tipi koordinatoru ekleyelim").
 *
 * assign(): tipin gecerli koordinatoru ayni kisiyse bir sey yapilmaz; baska
 * biriyse eski atama kapanir (valid_until) ve yeni atama acilir. remove():
 * gecerli atamayi kapatir. Atama satiri silinmez; gecmis Personel
 * Hareketleri'nde ve kapanan satirlarda kalir.
 */
final class ProjectTypeCoordinatorService extends AbstractService
{
    protected string $model = ProjectTypeCoordinator::class;

    protected string $orderBy = 'scope_type';

    public function assign(ProjectScopeType|string $type, int $personnelId): ProjectTypeCoordinator
    {
        $type = $type instanceof ProjectScopeType ? $type : ProjectScopeType::from($type);

        return $this->transactions->run(function () use ($type, $personnelId): ProjectTypeCoordinator {
            $personnel = Personnel::query()->whereKey($personnelId)->first();

            if (! $personnel instanceof Personnel || ! $personnel->isActive()) {
                throw CoordinatorNotActiveException::make();
            }

            $current = $this->currentRow($type);

            if ($current !== null && (int) $current->getAttribute('personnel_id') === $personnelId) {
                return $current;
            }

            $now = Carbon::now();

            if ($current !== null) {
                $this->close($current, $now);
            }

            /** @var ProjectTypeCoordinator $created */
            $created = $this->create([
                'scope_type' => $type->value,
                'personnel_id' => $personnelId,
                'valid_from' => $now,
            ]);

            ProjectTypeCoordinatorQueries::flush();

            return $created;
        });
    }

    /** Tipin gecerli koordinatorunu kaldirir; yoksa false. */
    public function remove(ProjectScopeType|string $type): bool
    {
        $type = $type instanceof ProjectScopeType ? $type : ProjectScopeType::from($type);

        return $this->transactions->run(function () use ($type): bool {
            $current = $this->currentRow($type);

            if ($current === null) {
                return false;
            }

            $this->close($current, Carbon::now());
            ProjectTypeCoordinatorQueries::flush();

            return true;
        });
    }

    /** Atama satiri silinmez (gecmis kalir); remove() kullanilir. */
    public function delete(Model|int|string $record): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $type = $record->getAttribute('scope_type');
        $value = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        return [
            'proje_tipi' => ProjectScopeType::tryFrom($value)?->getLabel() ?? $value,
            'personnel_id' => $record->getAttribute('personnel_id'),
        ];
    }

    private function currentRow(ProjectScopeType $type): ?ProjectTypeCoordinator
    {
        /** @var ProjectTypeCoordinator|null */
        return ProjectTypeCoordinator::query()
            ->current()
            ->where('scope_type', $type->value)
            ->lockForUpdate()
            ->first();
    }

    private function close(ProjectTypeCoordinator $row, Carbon $at): void
    {
        $row->forceFill(['valid_until' => $at])->save();
        $this->recordActivity($row, 'ended', $this->createdChanges($row));
    }
}
