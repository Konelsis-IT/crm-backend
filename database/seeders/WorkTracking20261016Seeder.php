<?php

declare(strict_types=1);

namespace Database\Seeders;

use Spatie\Permission\PermissionRegistrar;
use App\Services\Authorization\PositionRoleSync;
use App\Models\Personnel\PositionAssignment;
use App\Models\Personnel\Position;
use App\Enums\Personnel\PositionStatus;
use App\Enums\Personnel\PersonnelStatus;
use App\Enums\Personnel\ReportingRelationType;
use App\Enums\Personnel\ReportingScopeType;
use App\Enums\Procurement\PurchaseNeedSource;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\UsageArea;
use App\Enums\Project\CriticalityProfile;
use App\Enums\Project\ProjectStatus;
use App\Enums\Report\RecurrenceFrequency;
use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemSource;
use App\Enums\Report\WorkItemStatus;
use App\Enums\Report\WorkWaitingKind;
use App\Models\Acquisition\BusinessCase;
use App\Models\Party\ContactRelationship;
use App\Models\Party\Party;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\PersonnelAssignment;
use App\Models\Personnel\ReportingRelationship;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Project\Project;
use App\Models\Report\RecurringWork;
use App\Models\Report\WorkItem;
use App\Services\Audit\ActorContext;
use App\Services\Party\ContactRelationshipService;
use App\Services\Party\PartyRoleService;
use App\Services\Party\PartyService;
use App\Services\Platform\SchemaReadiness;
use App\Services\Procurement\PurchaseNeedService;
use App\Services\Project\ProjectScopeService;
use App\Services\Project\ProjectService;
use App\Services\Report\RecurringWorkService;
use App\Services\Report\WorkItemService;
use App\Services\Support\TransactionRunner;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Support\Carbon;

/**
 * Is takip listesi ve proje alt kirim tablosu, 16.10.2026 (D-219; kullanici
 * talimati: "Faz 1'i uygula ... Faz 3 icin sen proje sayfasi pafta hesap
 * listesindeki guncel durumu yine is olarak ekle. Faz 3 icinde satin alma
 * kisminda satin alinacaklari yine ekle ... Faz 4 uygulayalim. Santral uretim
 * alani haric").
 *
 * Veri: database/seeders/data/work_tracking_2026_10_16.php (iki Excel'den
 * uretildi). Sira:
 *  1. Eksik personel (saha sefleri; MNG sevkiyat alicilari): e-posta ya da adla
 *     varsa dokunulmaz; birimi ve amiri yazilir. D-220 (kullanici: "Sisteme uygun
 *     atamalar yapmaya calis. Yanlista olsa ata."): Saha'da Santiye sefi ve
 *     Elektrik-elektronik muhendisi, Lojistik'te Lojistik sorumlusu gorevleri
 *     acilir; rolleri en yakin var olan gorevin (Insaat sorumlusu / Satin alma
 *     sorumlusu) izinleriyle kurulur ve yeni personel gorevine atanir.
 *  2. Taraflar (METGUN, ORKOM, TELEORMAN) ve var olan taraflara kisiler (Kisiler sayfasi).
 *  3. Eksik projeler: METGUN, Kirsehir TM / ENH (isveren Aksa Yenilenebilir, kullanici
 *     "simdilik dogru kabul ederek olustur"), Teleorman (D-220: "isverene de proje
 *     adina da teleorman yaz"; saha Romanya'da).
 *  4. Gorev ve sorumluluklar (Rapor Tanimlari) ve Rapor Kontrol donemleri.
 *  5. Isler (Isler + Hareketler notlari + alt kirim tablosu), proje dokumanlari
 *     ve satin alinacak kalemler (her kalemin bagli isi).
 *
 * Kisi e-postayla, proje kisa adla, potansiyel is basligiyla bulunur (asla id
 * ile degil). Var olan is (ayni eski is no) yeniden acilmaz. Sorumlusu
 * olmayan is Huseyin Gunes'e, "Tum ekip" isi personelden bagimsiz Tum ekip
 * isine yazilir; listeyi giren Huseyin Gunes oldugu icin aktarimi o yapar ve
 * talep eden odur (Verdigim isler'de izlenir).
 *
 * Her satir kendi islemindedir; hata verirse geri alinir, sonraki kurulumda
 * yeniden denenir. B57 uygulanmadan calismaz. preview() hicbir sey yazmaz.
 * Kurulumda DEPLOY_SEEDERS ile calisir.
 */
final class WorkTracking20261016Seeder extends ProtectedSeeder
{
    public const DATA_FILE = 'work_tracking_2026_10_16.php';

    public const HUSEYIN = 'huseyin.gunes@konelsis.com';

    /**
     * Saha ve lojistik gorevleri (D-220). 'like': izinleri kopyalanan var olan
     * gorev [departman kodu, gorev kodu] (RoleMatrixSeeder'da Saha, Insaat ile
     * ayni saha paketini alir; Lojistik siparis / sevkiyat isi Satin alma'ya yakin).
     *
     * @var array<string, array{unit: string, title: string, level: int, headcount: int, like: array{0: string, 1: string}}>
     */
    public const POSITIONS = [
        'SS' => ['unit' => 'SAHA', 'title' => 'Şantiye şefi', 'level' => 0, 'headcount' => 4, 'like' => ['INSAAT', 'INS']],
        'SEM' => ['unit' => 'SAHA', 'title' => 'Elektrik-elektronik mühendisi', 'level' => 0, 'headcount' => 2, 'like' => ['INSAAT', 'INS']],
        'LJS' => ['unit' => 'LOJISTIK', 'title' => 'Lojistik sorumlusu', 'level' => 0, 'headcount' => 2, 'like' => ['SATIN_ALMA', 'SAS']],
    ];

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    /** @var array<string, int|null> */
    private array $people = [];

    /** @var array<string, int|null> */
    private array $projects = [];

    /** @var array<string, int|null> */
    private array $units = [];

    /** @var array<string, int> */
    private array $totals = ['positions' => 0, 'assignments' => 0, 'roles' => 0, 'personnel' => 0, 'parties' => 0, 'contacts' => 0, 'projects' => 0, 'recurring' => 0, 'occurrences' => 0, 'works' => 0, 'notes' => 0, 'needs' => 0, 'adopted' => 0, 'skipped' => 0];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B57') || ! SchemaReadiness::hasBatch('B36')) {
            $this->command?->warn('B57 (is sisteminin yukseltilmesi) uygulanmamis; is takip listesi 16.10 atlandi.');

            return;
        }

        $actor = Personnel::query()->where('email', self::HUSEYIN)->first() ?? SystemAccountSeeder::actor();

        if ($actor instanceof Personnel) {
            app(ActorContext::class)->actAsPersonnel((int) $actor->getKey());
        }

        $data = $this->data();

        foreach ($data['personnel'] as $row) {
            $this->guarded('personnel:'.Personnel::normalizeEmail($row['email']), fn (): ?Personnel => $this->personnel($row));
        }

        // D-220: gorev atamalari ve rolleri.
        foreach ($data['personnel'] as $row) {
            if (filled($row['position'] ?? null)) {
                $this->guarded('assignment:'.Personnel::normalizeEmail($row['email']).'|'.$row['position'], fn (): ?PositionAssignment => $this->assignment($row));
            }
        }

        $this->guarded('position-roles:'.implode(',', array_keys(self::POSITIONS)), fn (): ?bool => $this->positionRoles($data['personnel']));

        foreach ($data['parties'] as $row) {
            $this->guarded('party:'.Projects20261008Seeder::key($row['name']), fn (): ?Party => $this->party($row));
        }

        foreach ($data['contacts'] as $row) {
            $this->guarded('contact:'.Projects20261008Seeder::key($row['party'][0].'|'.$row['name']), fn (): ?ContactRelationship => $this->contact($row));
        }

        foreach ($data['projects'] as $row) {
            $this->guarded('project:'.Projects20261008Seeder::key($row['short']), fn (): ?Project => $this->project($row));
        }

        foreach ($data['recurring'] as $row) {
            $this->guarded('recurring:'.$row['key'], fn (): ?RecurringWork => $this->recurring($row));
        }

        foreach ($data['occurrences'] as $row) {
            $this->guarded('occurrence:'.$row['key'], fn (): ?WorkItem => $this->occurrence($row));
        }

        foreach ($data['works'] as $key => $row) {
            $this->guarded('work:'.$key, fn (): ?WorkItem => $this->work($row));
        }

        foreach ($data['purchases'] as $key => $row) {
            $this->guarded('purchase:'.$key, fn (): ?PurchaseNeed => $this->purchase($row));
        }

        $this->command?->info(sprintf(
            'Is takip listesi 16.10: %d gorev, %d gorev atamasi, %d rol verildi, %d personel, %d taraf, %d kisi, %d proje, %d gorev, %d donem isi, %d is (%d not), %d satin alinacak kalem; %d var olan kayit benimsendi, %d satir atlandi.',
            $this->totals['positions'], $this->totals['assignments'], $this->totals['roles'], $this->totals['personnel'], $this->totals['parties'], $this->totals['contacts'], $this->totals['projects'], $this->totals['recurring'],
            $this->totals['occurrences'], $this->totals['works'], $this->totals['notes'], $this->totals['needs'], $this->totals['adopted'], $this->totals['skipped'],
        ));
    }

    /**
     * Yazmadan: kac satir, hangileri seed arsivinde, cozulemeyen kisi / proje /
     * potansiyel is / departman.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $data = $this->data();
        $missing = ['people' => [], 'projects' => [], 'cases' => [], 'units' => []];
        $check = function (array $row) use (&$missing): void {
            foreach ([$row['owner'], ...$row['assignees']] as $email) {
                if ($this->personId($email) === null) {
                    $missing['people'][$email] = true;
                }
            }

            if ($row['project'] !== null && $this->projectId($row['project']) === null) {
                $missing['projects'][$row['project']] = true;
            }

            if ($row['business_case'] !== null && $this->caseId($row['business_case']) === null) {
                $missing['cases'][$row['business_case']] = true;
            }

            if ($row['unit'] !== null && $this->unitId($row['unit']) === null) {
                $missing['units'][$row['unit']] = true;
            }
        };

        foreach ($data['works'] as $row) {
            $check($row);
        }

        foreach ($data['purchases'] as $row) {
            $check($row['work']);
        }

        $archived = 0;

        foreach (array_keys($data['works']) as $key) {
            $archived += $this->isArchived('work:'.$key) ? 1 : 0;
        }

        return [
            'personnel' => array_map(fn (array $row): array => ['name' => $row['name'], 'email' => $row['email'], 'position' => $row['position'] ?? null, 'exists' => $this->existingPerson($row) !== null], $data['personnel']),
            'positions' => array_map(fn (array $position): array => [
                'title' => $position['title'],
                'exists' => $this->positionModel($position['unit'], array_search($position, self::POSITIONS, true)) !== null,
                'role_like' => $this->positionModel($position['like'][0], $position['like'][1])?->role?->name,
            ], self::POSITIONS),
            'projects' => array_map(fn (array $row): array => ['short' => $row['short'], 'exists' => Projects20261008Seeder::existingProject($row['short']) !== null, 'party' => $this->findParty($row['party'])?->display_name], $data['projects']),
            'works' => count($data['works']),
            'works_in_seed_archive' => $archived,
            'purchases' => count($data['purchases']),
            'recurring' => count($data['recurring']),
            'occurrences' => count($data['occurrences']),
            'notes' => array_sum(array_map(fn (array $row): int => count($row['notes']), $data['works'])) + array_sum(array_map(fn (array $row): int => count($row['notes']), $data['occurrences'])),
            // Projeye "Teleorman" isveren bilinmedigi icin bu dosyada yok.
            'unresolved' => array_map('array_keys', $missing),
        ];
    }

    /**
     * Satir kendi isleminde; hata verirse geri alinir ve sonraki kurulumda
     * yeniden denenir (arsive dusmez).
     *
     * @template T
     *
     * @param  \Closure(): T  $write
     */
    private function guarded(string $key, \Closure $write): void
    {
        $this->row($key, function () use ($key, $write): mixed {
            try {
                return app(TransactionRunner::class)->run($write, 1);
            } catch (\Throwable $exception) {
                $this->totals['skipped']++;
                $this->command?->warn(sprintf('Is takip listesi 16.10 satir "%s" atlandi (sonra yeniden denenecek): %s', $key, $exception->getMessage()));

                return null;
            }
        });
    }

    /**
     * @param  array{name: string, email: string, unit: string, title: string|null, manager: string|null, note: string}  $row
     */
    private function personnel(array $row): ?Personnel
    {
        $existing = $this->existingPerson($row);

        if ($existing !== null) {
            // Adla bulunan kisi (e-postasi farkli olabilir) listedeki adresle de bulunur.
            $this->people[$row['email']] = (int) $existing->getKey();
            $this->totals['adopted']++;

            return $existing;
        }

        $unitId = $this->unitId($row['unit']);

        if ($unitId === null) {
            throw new \RuntimeException('Departman bulunamadi: '.$row['unit']);
        }

        $next = 1 + (int) collect(Personnel::query()->withoutGlobalScopes()->pluck('personnel_no'))
            ->map(fn (mixed $no): int => (int) preg_replace('/\D+/', '', (string) $no))
            ->max();

        $personnel = new Personnel([
            'full_name' => $row['name'],
            'email' => $row['email'],
            'job_title' => $row['title'],
            'org_unit_id' => $unitId,
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'status' => PersonnelStatus::Active,
            'personnel_no' => sprintf('KN-%04d', max(1001, $next)),
            'password' => RealPersonnelSeeder::PASSWORD,
        ]);
        $personnel->forceFill(['email_verified_at' => now(), 'password_changed_at' => now()]);
        $personnel->save();

        $today = Carbon::now('UTC')->toDateString();

        PersonnelAssignment::query()->create([
            'personnel_id' => $personnel->getKey(),
            'org_unit_id' => $unitId,
            'job_title' => $row['title'],
            'effective_from' => $today,
            'note' => 'İş takip listesi 16.10.2026 (Kişiler): '.$row['note'],
        ]);

        $managerId = $row['manager'] !== null ? $this->personId($row['manager']) : null;

        if ($managerId !== null) {
            (new ReportingRelationship([
                'personnel_id' => $personnel->getKey(),
                'manager_personnel_id' => $managerId,
                'relation_type' => ReportingRelationType::Line,
                'scope_type' => ReportingScopeType::All,
                'valid_from' => $today,
            ]))->save();
        }

        $this->people[$row['email']] = (int) $personnel->getKey();
        $this->totals['personnel']++;

        return $personnel;
    }

    /**
     * Yeni personelin gorevi (D-220). Kisinin acik bir gorevi varsa dokunulmaz
     * (canlida elle atanmis olabilir).
     *
     * @param  array{email: string, position: string}  $row
     */
    private function assignment(array $row): ?PositionAssignment
    {
        $personnelId = $this->personId($row['email']);
        $definition = self::POSITIONS[$row['position']] ?? null;

        if ($personnelId === null || $definition === null) {
            throw new \RuntimeException('Personel ya da gorev bulunamadi: '.$row['email'].' / '.$row['position']);
        }

        /** @var PositionAssignment|null $open */
        $open = PositionAssignment::query()->where('personnel_id', $personnelId)->whereNull('valid_until')->orderByDesc('is_primary')->first();

        if ($open !== null) {
            $this->totals['adopted']++;

            return $open;
        }

        $position = $this->positionModel($definition['unit'], $row['position']);

        if ($position === null) {
            $unitId = $this->unitId($definition['unit']);

            if ($unitId === null) {
                throw new \RuntimeException('Departman bulunamadi: '.$definition['unit']);
            }

            $position = Position::query()->create([
                'org_unit_id' => $unitId,
                'code' => $row['position'],
                'title' => $definition['title'],
                'managerial_level' => $definition['level'],
                'headcount' => $definition['headcount'],
                'status' => PositionStatus::Active,
                'valid_from' => Carbon::now('UTC')->toDateString(),
            ]);
            $this->totals['positions']++;
        }

        /** @var PositionAssignment $assignment */
        $assignment = PositionAssignment::query()->create([
            'personnel_id' => $personnelId,
            'position_id' => $position->getKey(),
            'is_primary' => true,
            'allocation_pct' => 100,
            'valid_from' => Carbon::now('UTC')->toDateString(),
        ]);
        $this->totals['assignments']++;

        return $assignment;
    }

    /**
     * Yeni gorevlerin rolleri (D-220): rol yoksa acilir (ad = gorev basligi);
     * izni hic olmayan rol benzer gorevin rolunun izinlerini alir; rol yalniz bu
     * listedeki personele verilir (baska gorev sahiplerine dokunulmaz).
     *
     * @param  list<array<string, mixed>>  $people
     */
    private function positionRoles(array $people): ?bool
    {
        $sync = app(PositionRoleSync::class);

        if (! $sync->isReady()) {
            return null;
        }

        foreach (self::POSITIONS as $code => $definition) {
            $position = $this->positionModel($definition['unit'], $code);

            if ($position === null) {
                // Gorev henuz acilmadi (personel satiri sonraki kurulumda): yeniden denenir.
                return null;
            }

            $role = $sync->ensureRoleFor($position);
            $like = $this->positionModel($definition['like'][0], $definition['like'][1])?->role;

            if ($role !== null && $like !== null && $role->permissions()->doesntExist()) {
                $role->syncPermissions($like->permissions);
            }

            foreach ($people as $row) {
                if (($row['position'] ?? null) !== $code || $role === null) {
                    continue;
                }

                $personnel = Personnel::query()->where('normalized_email', Personnel::normalizeEmail((string) $row['email']))->first();
                $holds = $personnel !== null && PositionAssignment::query()
                    ->where('personnel_id', $personnel->getKey())
                    ->where('position_id', $position->getKey())
                    ->whereNull('valid_until')
                    ->exists();

                if ($personnel instanceof Personnel && $holds && ! $personnel->hasRole($role)) {
                    $personnel->assignRole($role);
                    $this->totals['roles']++;
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return true;
    }

    private function positionModel(string $unitCode, string $code): ?Position
    {
        $unitId = $this->unitId($unitCode);

        /** @var Position|null */
        return $unitId === null ? null : Position::query()->with('role')->where('org_unit_id', $unitId)->where('code', $code)->first();
    }

    /** @param  array{name: string, email: string}  $row */
    private function existingPerson(array $row): ?Personnel
    {
        /** @var Personnel|null $byEmail */
        $byEmail = Personnel::query()->withoutGlobalScopes()->where('normalized_email', Personnel::normalizeEmail($row['email']))->first();

        if ($byEmail !== null) {
            return $byEmail;
        }

        $key = FirmaTakipUpdate20261007Seeder::personKey($row['name']);

        foreach (Personnel::query()->get(['id', 'full_name']) as $person) {
            if (FirmaTakipUpdate20261007Seeder::personKey((string) $person->full_name) === $key) {
                return Personnel::query()->find($person->getKey());
            }
        }

        return null;
    }

    /** @param  array{name: string, role: string, country: string, note: string}  $row */
    private function party(array $row): ?Party
    {
        $existing = $this->findParty([$row['name']]);

        if ($existing !== null) {
            $this->totals['adopted']++;

            return $existing;
        }

        /** @var Party $party */
        $party = app(PartyService::class)->create([
            'party_kind' => 'organization',
            'display_name' => $row['name'],
            'country_code' => $row['country'],
            'status' => 'active',
            'organization_profile' => ['legal_name' => $row['name']],
            ...(SchemaReadiness::hasBatch('B28') ? ['network_note' => $row['note']] : []),
        ]);

        app(PartyRoleService::class)->create([
            'party_id' => $party->getKey(),
            'role_code' => $row['role'],
            'status' => 'active',
        ]);

        $this->totals['parties']++;

        return $party;
    }

    /** @param  array{party: list<string>, name: string, role: string, note: string, email: string|null}  $row */
    private function contact(array $row): ?ContactRelationship
    {
        $party = $this->findParty($row['party']);

        if ($party === null) {
            throw new \RuntimeException('Taraf bulunamadi: '.implode(' / ', $row['party']));
        }

        $key = FirmaTakipUpdate20261007Seeder::personKey($row['name']);

        foreach ($party->contacts as $contact) {
            if (FirmaTakipUpdate20261007Seeder::personKey($contact->displayName()) === $key) {
                $this->totals['adopted']++;

                return $contact;
            }
        }

        $contact = app(ContactRelationshipService::class)->quickCreate((int) $party->getKey(), [
            'contact_name' => $row['name'],
            'relationship_role' => $row['role'],
            'department_note' => $row['note'],
            'email' => $row['email'],
        ]);
        $this->totals['contacts']++;

        return $contact;
    }

    /** @param  array{short: string, party: list<string>, manager: string, types: list<string>, type_code: string|null, note: string, country?: string}  $row */
    private function project(array $row): ?Project
    {
        $existing = Projects20261008Seeder::existingProject($row['short']);

        if ($existing !== null) {
            $this->totals['adopted']++;

            return $existing;
        }

        $party = $this->findParty($row['party']);
        $managerId = $this->personId($row['manager']);

        if ($party === null || $managerId === null) {
            throw new \RuntimeException('Proje tarafi ya da muduru bulunamadi: '.$row['short']);
        }

        /** @var Project $project */
        $project = app(ProjectService::class)->createDirect([
            'name' => $row['short'],
            'short_name' => $row['short'],
            'customer_party_id' => (int) $party->getKey(),
            'project_manager_employee_id' => $managerId,
            'project_type_code' => $row['type_code'] ?? ProjectScopeService::componentCodeFor($row['types']),
            'criticality_profile' => CriticalityProfile::Standard->value,
            'status' => ProjectStatus::Active->value,
            'country_code' => $row['country'] ?? 'TR',
            'site_country_code' => $row['country'] ?? 'TR',
            'currency_code' => (string) config('konelsis.organization.default_currency', 'TRY'),
            'description' => implode("\n", [
                'İş takip listesinden aktarıldı (16.10.2026, Projeler sayfası).',
                $row['note'],
                'Lisans adı bilinmediği için kısa adla aynı yazıldı; lisans adı elle düzeltilecek.',
            ]),
            'scope_types' => $row['types'],
            'scopes' => [],
        ]);

        $this->projects[$row['short']] = (int) $project->getKey();
        $this->totals['projects']++;

        return $project;
    }

    /** @param  array<string, mixed>  $row */
    private function recurring(array $row): ?RecurringWork
    {
        $existing = $this->recurringByTitle((string) $row['title']);

        if ($existing !== null) {
            $this->totals['adopted']++;

            return $existing;
        }

        $personnelId = $this->personId((string) $row['personnel']) ?? $this->personId(self::HUSEYIN);

        $work = app(RecurringWorkService::class)->import([
            'title' => $row['title'],
            'description' => $row['description'],
            'kind' => $row['kind'],
            'frequency' => $row['frequency'],
            'personnel_id' => $personnelId,
            'org_unit_id' => $row['unit'] !== null ? $this->unitId((string) $row['unit']) : null,
            'category_code' => $row['category'],
            'priority' => 'normal',
            'is_active' => (bool) $row['is_active'],
            'starts_on' => $row['starts_on'],
        ]);

        $this->totals['recurring']++;

        return $work;
    }

    /** @param  array{key: string, definition: string, day: string, status: string, notes: list<array{body: string, source: string|null, noted_at: string|null}>}  $row */
    private function occurrence(array $row): ?WorkItem
    {
        $definition = collect($this->data()['recurring'])->firstWhere('key', $row['definition']);
        $work = $definition !== null ? $this->recurringByTitle((string) $definition['title']) : null;

        if (! $work instanceof RecurringWork || ! $work->frequency instanceof RecurrenceFrequency) {
            return null;
        }

        $day = CarbonImmutable::parse($row['day'], 'Europe/Istanbul');
        $key = $work->frequency->periodKey($day);
        /** @var WorkItem|null $existing */
        $existing = WorkItem::query()->where('recurring_work_id', $work->getKey())->where('period_key', $key)->first();

        if ($existing !== null) {
            $this->totals['adopted']++;

            return $existing;
        }

        $item = app(RecurringWorkService::class)->openPeriod($work, $day, WorkItemStatus::from($row['status']));

        if (! $item instanceof WorkItem) {
            return null;
        }

        foreach ($row['notes'] as $note) {
            app(WorkItemService::class)->addNote($item, $note['body'], $note['source'], $note['noted_at']);
            $this->totals['notes']++;
        }

        $this->totals['occurrences']++;

        return $item;
    }

    /** @param  array<string, mixed>  $row */
    private function work(array $row, ?int $purchaseNeedId = null): ?WorkItem
    {
        /** @var WorkItem|null $existing */
        $existing = WorkItem::query()->where('legacy_no', $row['legacy_no'])->first();

        if ($existing !== null) {
            $this->totals['adopted']++;

            return $existing;
        }

        $huseyin = $this->personId(self::HUSEYIN);
        // Sorumlu bulunamazsa satir sonraki kuruluma kalir (yanlis kisiye yazilmaz).
        $ownerId = $this->personId((string) $row['owner']);

        if ($ownerId === null) {
            throw new \RuntimeException('Sorumlu bulunamadi: '.$row['owner']);
        }

        $caseId = $row['business_case'] !== null ? $this->caseId((string) $row['business_case']) : null;
        $payload = [
            'title' => $row['title'],
            'personnel_id' => $ownerId,
            'assignee_ids' => array_values(array_filter(array_map(fn (string $email): ?int => $this->personId($email), $row['assignees']))),
            'for_everyone' => (bool) $row['for_everyone'],
            'org_unit_id' => $row['unit'] !== null ? $this->unitId((string) $row['unit']) : null,
            'project_id' => $row['project'] !== null ? $this->projectId((string) $row['project']) : null,
            'category_code' => $row['category'],
            'status' => $row['status'],
            'priority' => $row['priority'],
            'discipline' => $row['discipline'],
            'work_on' => $row['work_on'],
            'work_time' => '09:00',
            'due_on' => $row['due_on'],
            'closed_on' => $row['closed_on'],
            'note' => $row['note'],
            'legacy_no' => $row['legacy_no'],
            'source' => WorkItemSource::Manual->value,
            ...($row['status'] === WorkItemStatus::Waiting->value ? ['waiting_kind' => WorkWaitingKind::Text->value, 'waiting_text' => $row['waiting_text'] ?: 'Dönüş bekleniyor'] : []),
            ...($purchaseNeedId !== null
                ? ['link_kind' => WorkItemLinkKind::PurchaseNeed->value, 'link_id' => $purchaseNeedId]
                : ($caseId !== null ? ['link_kind' => WorkItemLinkKind::BusinessCase->value, 'link_id' => $caseId] : [])),
            // Listeyi Huseyin Gunes tuttu: baskasinin isinde talep eden odur.
            ...($huseyin !== null && $ownerId !== $huseyin && SchemaReadiness::hasBatch('B37')
                ? ['requester_kind' => WorkWaitingKind::Personnel->value, 'requester_personnel_id' => $huseyin]
                : []),
        ];

        $item = app(WorkItemService::class)->import($payload, $row['notes']);
        $this->totals['works']++;
        $this->totals['notes'] += count($row['notes']);

        return $item;
    }

    /** @param  array<string, mixed>  $row */
    private function purchase(array $row): ?PurchaseNeed
    {
        $legacy = (string) $row['work']['legacy_no'];
        /** @var WorkItem|null $linked */
        $linked = WorkItem::query()->where('legacy_no', $legacy)->first();

        if ($linked !== null && $linked->linked_purchase_need_id !== null) {
            $this->totals['adopted']++;

            return PurchaseNeed::query()->find($linked->linked_purchase_need_id);
        }

        $service = app(PurchaseNeedService::class);
        /** @var PurchaseNeed $need */
        $need = $service->create([
            'source' => PurchaseNeedSource::Manual->value,
            'usage_area' => UsageArea::Field->value,
            'requested_item_name' => $row['title'],
            'title' => $row['title'],
            'project_id' => $this->projectId((string) $row['project']),
            'org_unit_id' => $this->unitId('SATIN_ALMA'),
            'note' => $row['note'],
        ]);

        $listDay = Carbon::parse((string) $row['list_date'].' 09:00', 'Europe/Istanbul')->timezone('UTC');

        match ($row['status']) {
            'quoting' => $service->advance($need, PurchaseNeedStatus::Quoting, 'quoting'),
            'ordered' => $service->advance($need, PurchaseNeedStatus::Ordered, 'ordered', array_filter([
                'ordered_at' => $listDay,
                'expected_delivery_on' => $row['expected_on'],
            ], fn (mixed $value): bool => $value !== null)),
            'completed' => $service->advance($need, PurchaseNeedStatus::Completed, 'completed', ['completed_at' => $listDay]),
            default => $need,
        };

        $this->totals['needs']++;
        $this->work($row['work'], (int) $need->getKey());

        return $need;
    }

    private function recurringByTitle(string $title): ?RecurringWork
    {
        /** @var RecurringWork|null */
        return RecurringWork::query()->where('title', $title)->orderBy('id')->first();
    }

    private function personId(string $email): ?int
    {
        if (($this->people[$email] ?? null) === null) {
            $id = Personnel::query()->where('normalized_email', Personnel::normalizeEmail($email))->value('id');
            $this->people[$email] = $id !== null ? (int) $id : null;
        }

        return $this->people[$email];
    }

    private function projectId(string $short): ?int
    {
        if (! array_key_exists($short, $this->projects) || $this->projects[$short] === null) {
            $project = Projects20261008Seeder::existingProject($short);
            $this->projects[$short] = $project !== null ? (int) $project->getKey() : null;
        }

        return $this->projects[$short];
    }

    private function unitId(string $code): ?int
    {
        if (! array_key_exists($code, $this->units)) {
            $id = OrgUnit::query()->where('code', $code)->value('id');
            $this->units[$code] = $id !== null ? (int) $id : null;
        }

        return $this->units[$code];
    }

    private function caseId(string $title): ?int
    {
        $id = BusinessCase::query()->where('title', $title)->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }

    /** @param  list<string>  $names */
    private function findParty(array $names): ?Party
    {
        return (new FirmaTakipUpdate20261007Seeder)->findParty($names);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        /** @var array<string, mixed> */
        return $this->data ??= require __DIR__.'/data/'.self::DATA_FILE;
    }
}
