<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Exceptions\CodeAlreadyInUseException;
use App\Exceptions\Personnel\SelfParentNotAllowedException;
use App\Models\Personnel\OrgUnit;
use App\Models\Reference\LegalEntity;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Organizasyon birimi servisi.
 *
 * create ve update override edilmistir: kod arayuzde sorulmadigi icin
 * addan uretilir (departmandaki D-51 davranisinin aynisi), tek kayitli
 * legal_entity otomatik atanir, ve "Ust Birim" secimi org_unit_relations'a
 * tarihceli olarak yazilir (SyncOrgUnitParent).
 */
final class OrgUnitService extends AbstractService
{
    /** @var list<string> */
    protected array $with = ['legalEntity', 'manager'];

    protected string $orderBy = 'name';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly SyncOrgUnitParent $parentSync,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            $name = trim((string) ($data['name'] ?? ''));

            $code = filled($data['code'] ?? null)
                ? strtoupper(trim((string) $data['code']))
                : $this->generateCode($name);

            if (OrgUnit::query()->where('code', $code)->exists()) {
                throw CodeAlreadyInUseException::make(['code' => $code]);
            }

            $parentId = $this->extractParentId($data);

            /** @var OrgUnit $unit */
            $unit = parent::create([
                ...$data,
                'code' => $code,
                'name' => $name,
                'legal_entity_id' => $data['legal_entity_id'] ?? $this->defaultLegalEntityId(),
                'unit_type' => $data['unit_type'] ?? 'department',
                'valid_from' => $data['valid_from'] ?? Carbon::now('UTC')->toDateString(),
            ]);

            $this->parentSync->syncIfChanged($unit, $parentId);

            return $unit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            /** @var OrgUnit $current */
            $current = $this->show($record);

            $parentProvided = array_key_exists('parent_org_unit_id', $data);
            $parentId = $this->extractParentId($data);

            if ($parentProvided && $parentId !== null) {
                $this->assertNoHierarchyCycle((int) $current->getKey(), $parentId);
            }

            // Kod arayuzde duzenlenmez.
            unset($data['code']);

            /** @var OrgUnit $unit */
            $unit = parent::update($current, $data);

            if ($parentProvided) {
                $change = $this->parentSync->syncIfChanged($unit, $parentId);

                if ($change !== null) {
                    $this->recordActivity($unit, 'parent_changed', ['ust_birim' => $change]);
                }
            }

            return $unit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractParentId(array &$data): ?int
    {
        $parentId = $data['parent_org_unit_id'] ?? null;
        unset($data['parent_org_unit_id']);

        return $parentId !== null && $parentId !== '' ? (int) $parentId : null;
    }

    /** Bir birim kendi (dogrudan veya zincirleme) ust birimi olamaz. */
    private function assertNoHierarchyCycle(int $unitId, int $candidateParentId): void
    {
        if ($candidateParentId === $unitId) {
            throw SelfParentNotAllowedException::make();
        }

        $current = OrgUnit::query()->find($candidateParentId);
        $depth = 0;

        while ($current !== null && $depth < 50) {
            $parent = $current->currentParent();

            if ($parent !== null && (int) $parent->getKey() === $unitId) {
                throw SelfParentNotAllowedException::make();
            }

            $current = $parent;
            $depth++;
        }
    }

    private function defaultLegalEntityId(): ?int
    {
        $code = (string) config('konelsis.legal_entity.code', 'KONELSIS_MAIN');

        return LegalEntity::query()->where('code', $code)->value('id')
            ?? LegalEntity::query()->value('id');
    }

    /**
     * Birim adindan benzersiz kod uretir: "Saha Operasyon" -> "SAHA-OPERASYON".
     * Ayni kod varsa sonuna sira numarasi eklenir.
     */
    private function generateCode(string $name): string
    {
        $base = Str::limit(strtoupper(Str::slug($name, '-')), 28, '');

        if ($base === '') {
            $base = 'BIRIM';
        }

        $code = $base;
        $suffix = 2;

        while (OrgUnit::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
