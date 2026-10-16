<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\AcquisitionStage;
use App\Models\Acquisition\BusinessCase;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Audit\ActorContext;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Firma takip listesi 07.10.2026'daki kirmizi satirlar = kaybedilen is (D-197;
 * kullanici 13 Ekim 2026: "Kirmizi satirlar evet kaybedildi yap"). D-170
 * aktariminda bu satirlarin durumuna dokunulmamisti.
 *
 * - AKDAG GRANIT: Elazig / Palu 6,99 MWm GES -> Kaybedildi.
 * - CMS JANT: Manisa Gordes ve Izmir Dikili GES (KKS listesinde de kirmizi,
 *   yerelde zaten Kaybedildi) -> Kaybedildi degilse Kaybedildi.
 *
 * Yalniz ileri: zaten Kaybedildi / Kazanildi / Iptal ya da Kaybedildi'ye
 * gecemeyen is degismez. Is bulunamazsa satir sonraki kurulumda yeniden
 * denenir. Guncelleme SeedGuard::allowingUpdates icinde, servis uzerinden
 * (etkinlik gecmisi yazilir). Kurulumda DEPLOY_SEEDERS ile calisir.
 */
final class FirmaTakipRedRowsLost20261013Seeder extends ProtectedSeeder
{
    private const REASON = 'firm_list_red';

    /** @var list<array{party: string, case: string}> Firma gorunen adinin ve is basliginin basi. */
    private const ROWS = [
        ['party' => 'AKDAĞ GRANİT', 'case' => 'Elazığ/Palu'],
        ['party' => 'CMS JANT', 'case' => 'MANİSA GÖRDES'],
        ['party' => 'CMS JANT', 'case' => 'İZMİR DİKİLİ'],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('business_cases')) {
            $this->command?->warn('Potansiyel is tablosu yok; kirmizi satirlar atlandi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $closed = 0;

        foreach (self::ROWS as $row) {
            $this->row('firma-takip-1007-red:'.$row['party'].'|'.$row['case'], function () use ($row, &$closed): ?BusinessCase {
                /** @var BusinessCase|null $case */
                $case = BusinessCase::query()
                    ->whereHas('primaryParty', fn (Builder $party): Builder => $party->where('display_name', 'like', $row['party'].'%'))
                    ->where('title', 'like', $row['case'].'%')
                    ->orderBy('id')
                    ->first();

                if ($case === null) {
                    return null;
                }

                $stage = $case->acquisition_stage;

                if ($stage === AcquisitionStage::Lost || ! $stage->canTransitionTo(AcquisitionStage::Lost)) {
                    return $case;
                }

                $closed++;

                return SeedGuard::allowingUpdates(
                    fn (): BusinessCase => app(BusinessCaseService::class)->changeStage($case, AcquisitionStage::Lost, self::REASON),
                );
            });
        }

        $this->command?->info(sprintf('Firma takip 07.10 kirmizi satirlar: %d potansiyel is Kaybedildi yapildi.', $closed));
    }
}
