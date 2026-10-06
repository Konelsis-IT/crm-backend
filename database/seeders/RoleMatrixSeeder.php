<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Authorization\Role;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use App\Services\Authorization\RoleResolver;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedPositionRoles;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rol - izin matrisi (D-90, 16 Eylul 2026 kullanici talimati).
 *
 * Roller gercek is rolleridir ve pozisyonlarla esittir: her pozisyonun kendi
 * rolu vardir (PositionRoleSync) ve o rolun gordugu ekranlar burada
 * departmanina gore belirlenir. Teknik "system_admin" rolu kaldirildi; tam
 * yetki Yonetici ve Gelistirici rollerindedir.
 *
 * Yetki paketleri:
 *  - ORTAK    : her personelin gordugu alanlar (pano, kendi raporlari,
 *               talepler, personel rehberi, organizasyon semasi).
 *  - Departman: o departmanin isi (proje, satin alma, ticari, finans, IK,
 *               saha, bilgi islem).
 *  - YONETICI : departman yoneticisi eki (onaylar, rapor inceleme, bildirim,
 *               sosyal medya icerik karari).
 *
 * Sosyal medya (D-106): sorumlu personelin yetkisi burada YAZILMAZ; modul
 * ayarlarinda secilen gorevi tutan kisi yetkisini politika uzerinden alir,
 * sorumlu gorev degisince bu seeder'i yeniden calistirmak gerekmez.
 *
 * Onkosul: Shield izinleri uretilmis olmali (php artisan shield:generate --all).
 * Izin bulunamazsa o satir sessizce atlanir; seeder izin URETMEZ.
 *
 * D-165: korumali seeder. Satirlar: 'role:<ad>' (tam yetkili roller ve
 * Denetci), 'role-holders:<ad>' (tam yetkili rol sahipleri),
 * 'position-role:<birim kodu>|<pozisyon kodu>'. Islenen satir arsive duser,
 * bir daha islenmez; izni ya da sahibi olan role dokunulmaz.
 */
class RoleMatrixSeeder extends ProtectedSeeder
{
    /** Okuma. */
    private const READ = ['ViewAny', 'View'];

    /** Okuma + kayit ekleme/duzenleme. */
    private const WRITE = ['ViewAny', 'View', 'Create', 'Update'];

    /** Okuma + yazma + silme. */
    private const MANAGE = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

    /**
     * Her rolde bulunan ortak alanlar.
     *
     * @return array<string, list<string>>
     */
    private function common(): array
    {
        return [
            'Dashboard' => ['View'],
            'AnnouncementsWidget' => ['View'],
            'MyAlertsWidget' => ['View'],
            // Kendi raporlarini yazar, gonderir, geri ceker.
            'Report' => ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Submit', 'Withdraw'],
            // Is panosu (B36, D-115): kendi kartlarini girer, tasir, elle girdigini siler.
            'WorkItem' => ['ViewAny', 'View', 'Create', 'Update', 'Delete'],
            // Talep acar, kendine geleni yonetir.
            'WorkRequest' => self::WRITE,
            // Rehber: kimin hangi departmanda oldugunu gorur.
            'Personnel' => self::READ,
            'OrgUnit' => self::READ,
            'Position' => self::READ,
            // Kendisiyle ilgili onay taleplerini gorur.
            'ApprovalRequest' => self::READ,
        ];
    }

    /**
     * Departman kodu => yetki paketi.
     *
     * @return array<string, array<string, list<string>>>
     */
    private function departments(): array
    {
        $project = [
            'ProjectGroup' => ['View'],
            'Project' => self::MANAGE,
            'ProjectStageInstance' => self::WRITE,
            'ProjectWorkstream' => self::WRITE,
            'ProjectSupplyItem' => self::WRITE,
            'WbsNode' => self::WRITE,
            'WorkPackage' => self::WRITE,
            'DelayEvent' => self::WRITE,
            'FocusExpectation' => self::READ,
            'DepartmentHandoff' => self::WRITE,
            'DepartmentHandoffVersion' => self::WRITE,
            'StageNode' => self::READ,
            'StageTemplate' => self::READ,
            'StageTemplateVersion' => self::READ,
            'ComponentDefinition' => self::READ,
            'Documents' => ['View'],
            'Document' => self::WRITE,
            'Transmittal' => self::WRITE,
            'Party' => self::READ,
        ];

        $field = [
            'ProjectGroup' => ['View'],
            'Project' => self::READ,
            'ProjectStageInstance' => self::WRITE,
            'ProjectWorkstream' => self::READ,
            'ProjectSupplyItem' => self::READ,
            'WorkPackage' => self::WRITE,
            'DelayEvent' => self::WRITE,
            'ComponentDefinition' => self::READ,
            'Documents' => ['View'],
            'Document' => self::WRITE,
        ];

        $commercial = [
            'Party' => self::MANAGE,
            'BusinessCase' => self::MANAGE,
            'Proposal' => self::MANAGE,
            'ProposalVersion' => self::WRITE,
            'EstimateVersion' => self::WRITE,
            'TenderNotice' => self::MANAGE,
            'TenderNoticeVersion' => self::WRITE,
            'TenderSource' => self::WRITE,
            // Ihaleler kumesi ve faaliyet alani katalogu (B33, D-107).
            'Tenders' => ['View'],
            'ActivityArea' => self::WRITE,
            // Gorusme plani (B34, D-109)
            'MeetingPlan' => self::MANAGE,
            'Settings' => ['View'],
            'Contract' => self::WRITE,
            'ContractVersion' => self::WRITE,
            'OperationHandoff' => self::WRITE,
            'OperationHandoffVersion' => self::WRITE,
            'ProjectGroup' => ['View'],
            'Project' => self::READ,
            'ComponentDefinition' => self::READ,
            'Documents' => ['View'],
            'Document' => self::WRITE,
        ];

        return [
            'PROJE' => $project,
            'ELEKTRIK' => $field,
            'INSAAT' => $field,
            'ATOLYE' => $field,
            'SAHA' => $field,
            'IS_GELISTIRME' => $commercial,
            'TEKLIF' => $commercial,
            'SATIN_ALMA' => [
                'Procurement' => ['View'],
                'ProjectSupplyItem' => self::MANAGE,
                'ProjectGroup' => ['View'],
                'Project' => self::READ,
                'Party' => self::WRITE,
                'Contract' => self::READ,
                'Proposal' => self::READ,
                'ComponentDefinition' => self::READ,
                'Documents' => ['View'],
                'Document' => self::WRITE,
            ],
            'MUHASEBE' => [
                'Contract' => self::READ,
                'ContractVersion' => self::READ,
                'Proposal' => self::READ,
                'ProposalVersion' => self::READ,
                'EstimateVersion' => self::READ,
                'Party' => self::READ,
                'BusinessCase' => self::READ,
                'ProjectGroup' => ['View'],
                'Project' => self::READ,
                'ProjectSupplyItem' => self::READ,
                'Documents' => ['View'],
                'Document' => self::WRITE,
            ],
            'INSAN_KAYNAKLARI' => [
                'Personnel' => [...self::MANAGE, 'Notify'],
                'OrgUnit' => self::MANAGE,
                'Position' => self::MANAGE,
                'Training' => self::MANAGE,
                'Certification' => self::MANAGE,
                'Competency' => self::MANAGE,
                // Kisi degerlendirmesi yazar ve gizli raporlari gorur (D-86).
                'Report' => ['Review', 'ViewConfidential', 'AuthorHrEvaluation'],
                // Haftalik kontrol matrisini doldurur, Dikkat kartini gorur (B36, D-115).
                'WorkItem' => ['ControlMatrix', 'ViewAttentionCard'],
                'Settings' => ['View'],
                'Documents' => ['View'],
                'Document' => self::WRITE,
            ],
            'YAZILIM' => [
                'Documents' => ['View'],
                'Document' => self::MANAGE,
                'DocumentType' => self::WRITE,
                'DocumentTemplate' => self::WRITE,
                'ProjectGroup' => ['View'],
                'Project' => self::READ,
                'ComponentDefinition' => self::READ,
                'Settings' => ['View'],
            ],
            'BILGI_ISLEM' => [
                'Documents' => ['View'],
                'Document' => self::WRITE,
                'DocumentType' => self::READ,
                'Project' => self::READ,
                'ProjectGroup' => ['View'],
                'Settings' => ['View'],
            ],
            // Yonetim: tum is ekranlarini okur, onaylari ve duyurulari yonetir.
            'YONETIM' => [
                'ProjectGroup' => ['View'],
                'Procurement' => ['View'],
                'Tenders' => ['View'],
                'MeetingPlan' => self::WRITE,
                'Documents' => ['View'],
                'Settings' => ['View'],
                'Project' => self::WRITE,
                'ProjectStageInstance' => self::READ,
                'ProjectWorkstream' => self::READ,
                'ProjectSupplyItem' => self::READ,
                'WorkPackage' => self::READ,
                'DelayEvent' => self::READ,
                'Party' => self::WRITE,
                'BusinessCase' => self::WRITE,
                'Proposal' => self::WRITE,
                'Contract' => self::WRITE,
                'TenderNotice' => self::READ,
                'Document' => self::WRITE,
                'Transmittal' => self::READ,
                'ComponentDefinition' => self::READ,
                'ApprovalPolicy' => self::READ,
                'Delegation' => self::WRITE,
                'All' => ['Notify'],
                // Sosyal medya (D-106): icerik hazirlar ve duzenler. Karar izni
                // yonetici ekindedir; sorumlu personel yetkisini gorevinden alir.
                'SocialContent' => self::WRITE,
                // Is panosu (B36, D-115): Yonetim panosu, butun kartlar, Dikkat karti.
                'WorkItem' => ['ViewAll', 'ViewAttentionCard'],
            ],
        ];
    }

    /**
     * Departman yoneticisi eki (managerial_level >= 2).
     *
     * @return array<string, list<string>>
     */
    private function managerExtras(): array
    {
        return [
            'ApprovalRequest' => self::WRITE,
            'ApprovalPolicy' => self::READ,
            'Delegation' => self::WRITE,
            // Ekibinin raporlarini inceler (D-86).
            'Report' => ['Review'],
            'Personnel' => ['Notify'],
            'Team' => ['Notify'],
            'Department' => ['Notify'],
            // Sosyal medya iceriklerini gorur ve karar verir: onay / ret /
            // revize (D-106). Kendi olusturdugu icerige karar veremez.
            'SocialContent' => [...self::READ, 'Approve'],
        ];
    }

    /**
     * Denetci: sirket genelinde salt okuma.
     *
     * @return array<string, list<string>>
     */
    private function auditor(): array
    {
        $read = [];

        foreach ([
            'Project', 'ProjectStageInstance', 'ProjectWorkstream', 'ProjectSupplyItem',
            'WorkPackage', 'WbsNode', 'DelayEvent', 'Party', 'BusinessCase', 'Proposal',
            'ProposalVersion', 'Contract', 'ContractVersion', 'TenderNotice', 'Document',
            'Transmittal', 'Personnel', 'OrgUnit', 'Position', 'ApprovalRequest',
            'ApprovalPolicy', 'WorkRequest', 'Report', 'SocialContent', 'ActivityArea', 'MeetingPlan',
        ] as $subject) {
            $read[$subject] = self::READ;
        }

        return [...$read, 'Dashboard' => ['View'], 'Documents' => ['View'], 'ProjectGroup' => ['View'], 'Procurement' => ['View'], 'Tenders' => ['View']];
    }

    public function run(): void
    {
        if (! Schema::hasTable('permissions') || Permission::query()->doesntExist()) {
            $this->command?->warn('Shield izinleri yok; once "php artisan shield:generate --all" calistirin. RoleMatrixSeeder atlandi.');

            return;
        }

        // D-165: yalniz seed'in ekledigi pozisyon ve gorevler (syncAll alinan rolu geri veriyordu).
        SeedPositionRoles::sync();

        $all =Permission::query()->pluck('name')->all();

        // D-165 (6 Ekim 2026 kullanici talimati: "birine bir yetki verildiyse o
        // neden bozuluyor? Bozulmamalidir, canlida son hali neyse o kalmalidir"):
        // matris yalniz HIC izni olmayan role yazilir (ilk kurulum ya da yeni
        // pozisyon). Izni olan role dokunulmaz; ekrandan verilen / alinan
        // yetkiler korunur. Rol sahipleri de yalniz rolun hic sahibi yokken
        // atanir. Sonradan eklenecek izinler yeni bir seeder dosyasinda,
        // yalniz ekleyerek (givePermissionTo) verilir.
        $skipped = 0;

        // Tam yetkili roller: her izne sahip.
        foreach (SystemAccountSeeder::fullAccess() as $roleName => $emails) {
            $role = $this->row('role:'.$roleName, function () use ($roleName, $all, &$skipped): Role {
                $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

                if ($this->untouched($role)) {
                    $role->syncPermissions($all);
                } else {
                    $skipped++;
                }

                return $role;
            });

            // D-165: sahipler yalniz rolun hic sahibi yokken atanir; bulunamayan
            // kisi varsa satir arsive dusmez, sonraki seed'de yeniden denenir.
            $this->row('role-holders:'.$roleName, function () use ($role, $roleName, $emails): ?Role {
                $role ??= Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

                if ($role === null) {
                    return null;
                }

                if ($role->users()->exists()) {
                    return $role;
                }

                return $this->assignHolders($role, $emails) ? $role : null;
            });
        }

        $this->row('role:'.RoleResolver::AUDITOR, function () use (&$skipped): Role {
            $auditorRole = Role::query()->firstOrCreate(['name' => RoleResolver::AUDITOR, 'guard_name' => 'web']);

            if ($this->untouched($auditorRole)) {
                $auditorRole->syncPermissions($this->permissionNames($this->auditor()));
            } else {
                $skipped++;
            }

            return $auditorRole;
        });

        // Pozisyon rolleri: departman paketi + ortak alanlar (+ yonetici eki).
        $departments = $this->departments();
        $assigned = 0;

        foreach (Position::query()->with(['orgUnit', 'role'])->get() as $position) {
            $unitCode = (string) ($position->orgUnit?->code ?? '');
            $key = 'position-role:'.$unitCode.'|'.(string) $position->code;

            $this->row($key, function () use ($position, $unitCode, $departments, &$assigned, &$skipped): ?Role {
                $role = $position->role;

                // Rolu henuz yok: arsive dusmez, sonra yeniden denenir.
                if ($role === null) {
                    return null;
                }

                if (! $this->untouched($role)) {
                    $skipped++;

                    return $role;
                }

                $matrix = $this->common();

                foreach ($departments[$unitCode] ?? [] as $subject => $abilities) {
                    $matrix[$subject] = [...($matrix[$subject] ?? []), ...$abilities];
                }

                if ((int) $position->managerial_level >= 2) {
                    foreach ($this->managerExtras() as $subject => $abilities) {
                        $matrix[$subject] = [...($matrix[$subject] ?? []), ...$abilities];
                    }
                }

                $role->syncPermissions($this->permissionNames($matrix));
                $assigned++;

                return $role;
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf('%d pozisyon rolunun yetkileri yazildi; izni olan %d role dokunulmadi.', $assigned, $skipped));
    }

    /** Rolun hic izni yok mu (ilk kurulum ya da yeni pozisyon rolu). */
    private function untouched(Role $role): bool
    {
        return $role->permissions()->doesntExist();
    }

    /**
     * Konu => yetenek listesini var olan Shield izin adlarina cevirir.
     *
     * @param  array<string, list<string>>  $matrix
     * @return list<string>
     */
    private function permissionNames(array $matrix): array
    {
        static $existing = null;
        $existing ??= Permission::query()->pluck('name')->flip();

        $names = [];

        foreach ($matrix as $subject => $abilities) {
            foreach (array_unique($abilities) as $ability) {
                $name = $ability.':'.$subject;

                if ($existing->has($name)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Rol sahiplerini atar; hepsi bulunduysa true doner.
     *
     * @param  list<string>  $emails
     */
    private function assignHolders(Role $role, array $emails): bool
    {
        $complete = true;

        foreach ($emails as $email) {
            $normalized = Personnel::normalizeEmail($email);
            $personnel = Personnel::query()->where('normalized_email', $normalized)->first();

            if ($personnel === null) {
                $this->command?->warn(sprintf('%s bulunamadi; "%s" rolu atanmadi.', $email, $role->name));
                $complete = false;

                continue;
            }

            $personnel->assignRole($role);
        }

        return $complete;
    }
}
