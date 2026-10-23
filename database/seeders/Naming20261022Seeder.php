<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Personnel\OrgUnit;
use App\Models\Project\OperationGroupDefinition;
use App\Services\Audit\ActorContext;
use App\Services\Personnel\OrgUnitService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\OperationGroupDefinitionService;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedGuard;

/**
 * Ad duzeltmesi (D-234; kullanici 22 Ekim 2026: "Proje adımını Taahhüt
 * yapalım. Proje grubunu da projelendirme yapalım."). Ana is (PRJ kaydi)
 * arayuzde "Taahhut" olarak cevirilerden okunur; burada veritabaninda adi
 * duran ekip yazilir:
 *
 * - "PROJE" kodlu departman: "Proje" -> "Projelendirme".
 * - "PROJECT" kodlu iş kolu (operasyon grubu): "Proje" -> "Projelendirme"
 *   (Ingilizcesi "Design engineering").
 *
 * Kodlar degismez. Ad yalniz hala ilk haliyle duruyorsa yazilir (sonradan elle
 * verilmis ada dokunulmaz); kullanicinin istedigi tek guncelleme budur
 * (SeedGuard::allowingUpdates). Yeni kurulumda baslangic zinciri zaten yeni
 * adlarla kurar.
 */
final class Naming20261022Seeder extends ProtectedSeeder
{
    public function run(): void
    {
        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $renamed = 0;

        if (SchemaReadiness::hasBatch('B03')) {
            $this->row('org-unit-name:PROJE', function () use (&$renamed): ?OrgUnit {
                $unit = OrgUnit::query()->where('code', 'PROJE')->first();

                if (! $unit instanceof OrgUnit) {
                    return null;
                }

                if ((string) $unit->name === 'Proje') {
                    SeedGuard::allowingUpdates(static fn (): mixed => app(OrgUnitService::class)->update($unit, ['name' => 'Projelendirme']));
                    $renamed++;
                }

                return $unit;
            });
        }

        if (SchemaReadiness::hasBatch('B17')) {
            $this->row('operation-group-name:PROJECT', function () use (&$renamed): ?OperationGroupDefinition {
                $group = OperationGroupDefinition::query()->where('code', 'PROJECT')->first();

                if (! $group instanceof OperationGroupDefinition) {
                    return null;
                }

                if ((string) $group->name_tr === 'Proje') {
                    SeedGuard::allowingUpdates(static fn (): mixed => app(OperationGroupDefinitionService::class)->update($group, [
                        'name_tr' => 'Projelendirme',
                        'name_en' => (string) $group->name_en === 'Project' ? 'Design engineering' : $group->name_en,
                    ]));
                    $renamed++;
                }

                return $group;
            });
        }

        $this->command?->info(sprintf('Naming20261022Seeder: %d ad "Projelendirme" yapildi.', $renamed));
    }

    /**
     * Yazmadan: hangi adlar degisecek.
     *
     * @return array<string, string|null>
     */
    public function preview(): array
    {
        return [
            'org_unit_PROJE' => SchemaReadiness::hasBatch('B03') ? OrgUnit::query()->where('code', 'PROJE')->value('name') : null,
            'operation_group_PROJECT' => SchemaReadiness::hasBatch('B17') ? OperationGroupDefinition::query()->where('code', 'PROJECT')->value('name_tr') : null,
        ];
    }
}
