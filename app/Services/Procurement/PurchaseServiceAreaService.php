<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Exceptions\InvalidTransitionException;
use App\Models\Procurement\PurchaseServiceArea;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\CodeGenerator;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use App\Support\TurkishFold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tedarikci hizmet alanlari (D-232, B58): ad ve sira kullanicidan, kod
 * serviste uretilir (D-130). Silme yok, arsiv var (D-156).
 */
final class PurchaseServiceAreaService extends AbstractService
{
    protected string $model = PurchaseServiceArea::class;

    protected string $orderBy = 'sort_order';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly CodeGenerator $codes,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data  name, sort_order
     */
    public function create(array $data): Model
    {
        $name = trim((string) ($data['name'] ?? ''));

        return parent::create([
            'name' => $name,
            'code' => $this->codes->unique('HZA '.$name, PurchaseServiceArea::class, 'HZA', [], 64),
            'sort_order' => (int) ($data['sort_order'] ?? ((int) PurchaseServiceArea::query()->max('sort_order') + 10)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  name, sort_order, row_version
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return parent::update($record, array_intersect_key($data, array_flip(['name', 'sort_order', 'row_version'])));
    }

    /** Ayni adli alan (katlanmis ad) varsa onu, yoksa yenisini doner (aktarim). */
    public function ensure(string $name, int $sortOrder): PurchaseServiceArea
    {
        $key = TurkishFold::fold($name);

        foreach (PurchaseServiceArea::query()->get(['id', 'name']) as $area) {
            if (TurkishFold::fold((string) $area->getAttribute('name')) === $key) {
                return $area;
            }
        }

        /** @var PurchaseServiceArea $area */
        $area = $this->create(['name' => $name, 'sort_order' => $sortOrder]);

        return $area;
    }

    public function archive(Model|int|string $record): PurchaseServiceArea
    {
        return $this->transactions->run(function () use ($record): PurchaseServiceArea {
            /** @var PurchaseServiceArea $area */
            $area = $this->lockForUpdate($record);

            if ($area->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $area->forceFill(['archived_at' => Carbon::now('UTC')]);
            $this->saveWithoutVersion($area);
            $this->recordActivity($area, 'archived');

            return $area;
        });
    }

    public function restore(Model|int|string $record): PurchaseServiceArea
    {
        return $this->transactions->run(function () use ($record): PurchaseServiceArea {
            /** @var PurchaseServiceArea $area */
            $area = $this->lockForUpdate($record);

            if (! $area->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $area->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($area);
            $this->recordActivity($area, 'restored');

            return $area;
        });
    }
}
