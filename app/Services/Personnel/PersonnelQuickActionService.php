<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\PersonnelQuickAction;
use App\Services\AbstractService;
use App\Services\Platform\SchemaReadiness;

/**
 * Kisisel hizli islemleri kaydeder (B38, D-122).
 *
 * Kisinin kendi arayuz tercihidir; is kaydi olmadigi icin Personel
 * Hareketleri'ne satir yazilmaz. Secim her kayitta bastan yazilir (sira
 * secim sirasidir). Sabit islemler ve katalogda olmayan kodlar buraya
 * gelmeden once QuickActionCatalog tarafindan elenir.
 */
final class PersonnelQuickActionService extends AbstractService
{
    /**
     * @param  list<string>  $codes
     */
    public function sync(int $personnelId, array $codes): void
    {
        if (! SchemaReadiness::hasBatch('B38')) {
            return;
        }

        $codes = array_values(array_unique(array_filter($codes, fn ($code): bool => is_string($code) && $code !== '')));

        $this->transactions->run(function () use ($personnelId, $codes): void {
            PersonnelQuickAction::query()->where('personnel_id', $personnelId)->delete();

            foreach ($codes as $order => $code) {
                PersonnelQuickAction::query()->create([
                    'personnel_id' => $personnelId,
                    'action_code' => $code,
                    'sort_order' => $order,
                ]);
            }
        });
    }
}
