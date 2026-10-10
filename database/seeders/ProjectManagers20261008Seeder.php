<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Acquisition\ProjectScopeType;
use App\Models\Acquisition\BusinessCase;
use App\Models\Personnel\Personnel;
use App\Models\Project\Project;
use App\Models\Project\ProjectTypeCoordinator;
use App\Services\Acquisition\BusinessCaseService;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use App\Services\Project\ProjectService;
use App\Services\Project\ProjectTypeCoordinatorService;
use App\Services\Support\TransactionRunner;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedGuard;

/**
 * Devam eden projelerin proje mudurleri ve ad duzeltmeleri, 08.10.2026
 * (D-175, 8 Ekim 2026 kullanici duzeltmesi: "Proje yoneticisini Ersin Ozdemir
 * yapmissin ama yanlis cunku ne projelerde ne tekliflerde artik Ersin Ozdemir
 * yok. Ersin Bey sadece is gelistirme alaninda, yani yatirimci projeleri ve
 * potansiyel isler onun kontrolundedir").
 *
 * Projects20261008Seeder'in ilk hali (D-174) 13 projeyi Ersin Ozdemir'i proje
 * yoneticisi yaparak acmisti. Bu seeder o veriyle kurulmus ortamlari ileri
 * dogru duzeltir (D-169 kurali, SeedGuard::allowingUpdates); yeni ortamda
 * Projects20261008Seeder zaten dogru yazar ve burada degisecek bir sey kalmaz.
 *
 * Her proje bir satirdir (anahtar kisa ad; proje eski ya da yeni adla bulunur):
 * - Proje muduru yalniz HALA Ersin Ozdemir ise kullanicinin verdigi kisi olur
 *   (elle yapilmis bir degisiklik ezilmez).
 * - Ad duzeltmeleri (Projects20261008Seeder::RENAMES) kisa ad / lisans adi /
 *   is kaydi basligi HALA eski metinse yapilir.
 * - Projenin is kaydinin (createDirect) sahibi HALA Ersin ise proje muduru
 *   olur. Teklif sahipleri degismez (kullanici: teklif bilgisi vermedi).
 * - Proje bulunamazsa satir islenmez ve sonraki kurulumda yeniden denenir.
 *
 * Proje tipi koordinatoru (B49): GES -> Ertugrul Sahin, yalniz GES'in gecerli
 * koordinatoru yoksa. B49 uygulanmadiysa satir sonraki kuruluma kalir.
 *
 * Yazmalar servislerle (ProjectService::update, BusinessCaseService::update,
 * ProjectTypeCoordinatorService::assign), Personel Hareketleri'ne "Sistem"
 * olarak duser. Satir kendi transaction'indadir; hata verirse yalniz o satir
 * geri alinir. preview() hicbir sey yazmaz. Kurulumda DEPLOY_SEEDERS ile,
 * Projects20261008Seeder'dan sonra calisir.
 */
final class ProjectManagers20261008Seeder extends ProtectedSeeder
{
    /** Proje tipi koordinatorleri (D-175): tip => [e-posta, ad]. Digerleri ekrandan atanir. */
    public const COORDINATORS = [
        'ges' => ['ertugrul.sahin@konelsis.com', 'Ertuğrul Şahin'],
    ];

    private bool $dry = false;

    /** @var array<string, int> */
    private array $totals = ['managers' => 0, 'renames' => 0, 'case_owners' => 0, 'case_titles' => 0, 'coordinators' => 0, 'unchanged' => 0, 'missing' => 0, 'skipped' => 0];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B48') || ! SchemaReadiness::hasBatch('B17')) {
            $this->command?->warn('B48 uygulanmamis; proje mudurleri 08.10 atlandi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $ersinId = FirmaTakipUpdate20261007Seeder::ownerId();

        foreach (array_keys(Projects20261008Seeder::PROJECTS) as $short) {
            $this->row('manager:'.Projects20261008Seeder::key($short), function () use ($short, $ersinId): ?Project {
                try {
                    return SeedGuard::allowingUpdates(fn (): ?Project => app(TransactionRunner::class)->run(
                        fn (): ?Project => $this->project($short, $ersinId)['project'],
                        1,
                    ));
                } catch (\Throwable $exception) {
                    $this->totals['skipped']++;
                    $this->command?->warn(sprintf('Proje mudurleri 08.10 satir "%s" atlandi (sonra yeniden denenecek): %s', $short, $exception->getMessage()));

                    return null;
                }
            });
        }

        foreach (self::COORDINATORS as $type => $person) {
            $this->row('coordinator:'.$type, function () use ($type, $person): ?ProjectTypeCoordinator {
                try {
                    return app(TransactionRunner::class)->run(fn (): ?ProjectTypeCoordinator => $this->coordinator($type, $person)['coordinator'], 1);
                } catch (\Throwable $exception) {
                    $this->command?->warn(sprintf('Proje tipi koordinatoru %s atanamadi (sonra yeniden denenecek): %s', $type, $exception->getMessage()));

                    return null;
                }
            });
        }

        $this->command?->info(sprintf(
            'Proje mudurleri 08.10: %d proje muduru, %d ad duzeltmesi, %d is kaydi sahibi, %d is kaydi basligi, %d koordinator; %d degismedi, %d proje bulunamadi, %d satir atlandi.',
            $this->totals['managers'], $this->totals['renames'], $this->totals['case_owners'], $this->totals['case_titles'], $this->totals['coordinators'],
            $this->totals['unchanged'], $this->totals['missing'], $this->totals['skipped'],
        ));
    }

    /**
     * Yazmadan: her proje icin yapilacaklar ve koordinator atamasi.
     *
     * @return array{projects: list<array<string, mixed>>, coordinators: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function preview(): array
    {
        $this->dry = true;
        $ersinId = FirmaTakipUpdate20261007Seeder::ownerId();
        $projects = [];
        $coordinators = [];

        foreach (array_keys(Projects20261008Seeder::PROJECTS) as $short) {
            $result = $this->project($short, $ersinId);
            $project = $result['project'];
            $projects[] = [
                'short_name' => $short,
                'project' => $project !== null ? '#'.$project->getKey().' '.$project->short_name : null,
                'steps' => $result['steps'],
            ];
        }

        foreach (self::COORDINATORS as $type => $person) {
            $result = $this->coordinator($type, $person);
            $coordinators[] = ['type' => $type, 'steps' => $result['steps']];
        }

        $this->dry = false;

        return ['projects' => $projects, 'coordinators' => $coordinators, 'totals' => $this->totals];
    }

    /**
     * @return array{project: Project|null, steps: list<string>}
     */
    private function project(string $short, ?int $ersinId): array
    {
        $row = Projects20261008Seeder::PROJECTS[$short];
        $found = Projects20261008Seeder::existingProject($short);

        if ($found === null) {
            $this->totals['missing']++;

            return ['project' => null, 'steps' => ['proje bulunamadi (Projects20261008Seeder acacak; sonra yeniden denenecek)']];
        }

        /** @var Project $project */
        $project = Project::query()->findOrFail($found->getKey());
        $managerId = Projects20261008Seeder::managerId($row['manager']);
        $managerName = Projects20261008Seeder::MANAGERS[$row['manager']] ?? $row['manager'];
        $steps = [];
        $projectData = [];

        foreach ((array) ($row['aliases'] ?? []) as $old) {
            if ((string) $project->short_name === $old) {
                $projectData['short_name'] = $short;
                $steps[] = 'kisa ad: '.$old.' -> '.$short;
            }

            if ((string) $project->name === $old) {
                $projectData['name'] = $short;
                $steps[] = 'lisans adi: '.$old.' -> '.$short;
            }
        }

        if ($managerId === null) {
            $steps[] = 'proje muduru bulunamadi: '.$row['manager'];
        } elseif ($ersinId !== null && (int) $project->project_manager_employee_id === $ersinId) {
            $projectData['project_manager_employee_id'] = $managerId;
            $steps[] = 'proje muduru: Ersin Ozdemir -> '.$managerName;
        }

        $case = $project->business_case_id !== null ? BusinessCase::query()->find($project->business_case_id) : null;
        $caseData = [];

        if ($case instanceof BusinessCase) {
            foreach ((array) ($row['aliases'] ?? []) as $old) {
                if ((string) $case->title === $old) {
                    $caseData['title'] = $short;
                    $steps[] = 'is kaydi basligi: '.$old.' -> '.$short;
                }
            }

            if ($managerId !== null && $ersinId !== null && (int) $case->owner_employee_id === $ersinId) {
                $caseData['owner_employee_id'] = $managerId;
                $steps[] = 'is kaydi sahibi: Ersin Ozdemir -> '.$managerName;
            }
        }

        $renamed = array_key_exists('short_name', $projectData) || array_key_exists('name', $projectData);
        $this->totals['managers'] += array_key_exists('project_manager_employee_id', $projectData) ? 1 : 0;
        $this->totals['renames'] += $renamed ? 1 : 0;
        $this->totals['case_owners'] += array_key_exists('owner_employee_id', $caseData) ? 1 : 0;
        $this->totals['case_titles'] += array_key_exists('title', $caseData) ? 1 : 0;

        if ($steps === []) {
            $this->totals['unchanged']++;
            $steps[] = 'degisiklik yok';
        }

        if ($this->dry) {
            return ['project' => $project, 'steps' => $steps];
        }

        if ($projectData !== []) {
            /** @var Project $project */
            $project = app(ProjectService::class)->update($project, $projectData);
        }

        if ($case instanceof BusinessCase && $caseData !== []) {
            app(BusinessCaseService::class)->update($case, $caseData);
        }

        return ['project' => $project, 'steps' => $steps];
    }

    /**
     * @param  array{0: string, 1: string}  $person
     * @return array{coordinator: ProjectTypeCoordinator|null, steps: list<string>}
     */
    private function coordinator(string $type, array $person): array
    {
        if (! SchemaReadiness::hasBatch('B49')) {
            return ['coordinator' => null, 'steps' => ['B49 uygulanmamis; sonraki kurulumda atanacak']];
        }

        $scopeType = ProjectScopeType::from($type);
        /** @var ProjectTypeCoordinator|null $current */
        $current = ProjectTypeCoordinator::query()->current()->where('scope_type', $type)->with('personnel')->first();

        if ($current !== null) {
            return ['coordinator' => $current, 'steps' => ['zaten koordinatoru var: '.$current->personnel?->full_name.' (degismez)']];
        }

        $personnelId = $this->personnelId($person);

        if ($personnelId === null) {
            return ['coordinator' => null, 'steps' => ['personel bulunamadi: '.$person[0]]];
        }

        $steps = [$scopeType->getLabel().' koordinatoru: '.$person[1].' (#'.$personnelId.')'];

        if ($this->dry) {
            return ['coordinator' => null, 'steps' => $steps];
        }

        $coordinator = app(ProjectTypeCoordinatorService::class)->assign($scopeType, $personnelId);
        $this->totals['coordinators']++;

        return ['coordinator' => $coordinator, 'steps' => $steps];
    }

    /**
     * @param  array{0: string, 1: string}  $person
     */
    private function personnelId(array $person): ?int
    {
        [$email, $name] = $person;
        $id = Personnel::query()->where('email', $email)->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $key = FirmaTakipUpdate20261007Seeder::personKey($name);

        foreach (Personnel::query()->get(['id', 'full_name']) as $candidate) {
            if (FirmaTakipUpdate20261007Seeder::personKey((string) $candidate->full_name) === $key) {
                return (int) $candidate->getKey();
            }
        }

        return null;
    }
}
