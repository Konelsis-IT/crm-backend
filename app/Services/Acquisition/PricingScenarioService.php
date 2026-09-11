<?php

declare(strict_types=1);

namespace App\Services\Acquisition;

use App\Models\Acquisition\EstimateVersion;
use App\Models\Acquisition\PricingScenario;
use App\Services\AbstractService;
use Illuminate\Database\Eloquent\Model;

/**
 * Fiyat senaryosu servisi (10 SS3.10). Tahmin surumu basina tek secili
 * senaryo: secilen senaryo digerlerini dusurur ve toplam fiyati koke yazar.
 */
final class PricingScenarioService extends AbstractService
{
    protected string $model = PricingScenario::class;

    protected string $orderBy = 'scenario_code';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->transactions->run(function () use ($data): Model {
            /** @var PricingScenario $scenario */
            $scenario = parent::create([...$data, 'is_selected' => false]);

            if ((bool) ($data['is_selected'] ?? false)) {
                $this->select($scenario);
            }

            return $scenario;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): Model {
            $select = (bool) ($data['is_selected'] ?? false);
            unset($data['estimate_version_id'], $data['is_selected']);

            /** @var PricingScenario $scenario */
            $scenario = parent::update($record, $data);

            if ($select) {
                $this->select($scenario);
            }

            return $scenario;
        });
    }

    public function select(Model|int|string $record): PricingScenario
    {
        return $this->transactions->run(function () use ($record): PricingScenario {
            /** @var PricingScenario $scenario */
            $scenario = $this->lockForUpdate($record);

            PricingScenario::query()
                ->where('estimate_version_id', $scenario->estimate_version_id)
                ->whereKeyNot($scenario->getKey())
                ->where('is_selected', true)
                ->update(['is_selected' => false]);

            $scenario->forceFill(['is_selected' => true])->save();

            EstimateVersion::query()->whereKey($scenario->estimate_version_id)->update([
                'total_price' => $scenario->total_price,
                'target_margin_pct' => $scenario->target_margin_pct,
            ]);

            $this->recordActivity($scenario, 'selected');

            return $scenario;
        });
    }
}
