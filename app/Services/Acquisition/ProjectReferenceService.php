<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Exceptions\InvalidTransitionException;
use App\Exceptions\RecordArchivedException;
use App\Models\Acquisition\ProjectReference;
use App\Models\Acquisition\ProjectReferenceScopeType;
use App\Query\Acquisition\ProjectReferenceQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\Acquisition\ScopeTypes;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Referanslar (B50, D-177; 8 Ekim 2026 kullanici talimati: "referans eklemek
 * icin yeni Referans resource'u olustur; proje tipi de secilebilmeli ...
 * referans eklemesi de bu modal icinde action butonda olur").
 *
 * - create / update: referans metni (`title`) ve formdaki `scope_types`
 *   (coklu secim) tek transaction'da; tip satirlari eksikse eklenir,
 *   kaldirildiysa silinir. Yeni referans listenin sonuna eklenir
 *   (`sort_order`). Arsivdeki referans degistirilemez.
 * - archive / restore (D-156): yalniz archived_at; kimin yaptigi Personel
 *   Hareketleri'nde (project_reference.archived / .restored). Silme yok.
 */
final class ProjectReferenceService extends AbstractService
{
    protected string $orderBy = 'sort_order';

    /** @var list<string> */
    private const FIELDS = ['title', 'sort_order'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ProjectReferenceScopeTypeService $types,
        private readonly ProjectReferenceQueries $queries,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data  title, scope_types, (sort_order)
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var ProjectReference $reference */
            $reference = parent::create([
                ...$data,
                'sort_order' => filled($data['sort_order'] ?? null) ? (int) $data['sort_order'] : $this->queries->nextSortOrder(),
            ]);

            $this->syncTypes($reference, (array) ($data['scope_types'] ?? []));

            return $reference;
        });
    }

    /**
     * @param  array<string, mixed>  $data  title, scope_types, row_version
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var ProjectReference $current */
            $current = $this->lockForUpdate($record);

            if ($current->isArchived()) {
                throw RecordArchivedException::make();
            }

            /** @var ProjectReference $reference */
            $reference = parent::update($current, $data);

            if (array_key_exists('scope_types', $data)) {
                $this->syncTypes($reference, (array) $data['scope_types']);
            }

            return $reference;
        });
    }

    public function archive(Model|int|string $record): ProjectReference
    {
        return $this->transactions->run(function () use ($record): ProjectReference {
            /** @var ProjectReference $reference */
            $reference = $this->lockForUpdate($record);

            if ($reference->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $reference->forceFill(['archived_at' => Carbon::now('UTC')]);
            $this->saveWithoutVersion($reference);
            $this->recordActivity($reference, 'archived');

            return $reference;
        });
    }

    public function restore(Model|int|string $record): ProjectReference
    {
        return $this->transactions->run(function () use ($record): ProjectReference {
            /** @var ProjectReference $reference */
            $reference = $this->lockForUpdate($record);

            if (! $reference->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $reference->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($reference);
            $this->recordActivity($reference, 'restored');

            return $reference;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        $values = array_intersect_key($data, array_flip(self::FIELDS));

        if (array_key_exists('title', $values)) {
            $values['title'] = trim((string) $values['title']);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return ['referans' => Str::limit((string) $record->getAttribute('title'), 150)];
    }

    /**
     * Secilen tipleri referansla eslestirir: eksik tip eklenir, secimden
     * cikarilan tip satiri silinir (hareket kaydiyla).
     *
     * @param  list<mixed>  $types
     */
    private function syncTypes(ProjectReference $reference, array $types): void
    {
        $selected = ScopeTypes::values($types);
        $existing = [];

        foreach ($reference->scopeTypes()->get() as $row) {
            /** @var ProjectReferenceScopeType $row */
            $type = $row->getAttribute('scope_type');
            $existing[$type instanceof BackedEnum ? (string) $type->value : (string) $type] = $row;
        }

        foreach ($selected as $type) {
            if (! isset($existing[$type])) {
                $this->types->create(['project_reference_id' => $reference->getKey(), 'scope_type' => $type]);
            }
        }

        foreach ($existing as $type => $row) {
            if (! in_array($type, $selected, true)) {
                $this->types->delete($row);
            }
        }

        $reference->unsetRelation('scopeTypes');
    }
}
