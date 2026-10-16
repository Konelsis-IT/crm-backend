<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Authorization\Role;
use App\Models\Personnel\Position;
use App\Services\Authorization\RoleResolver;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Satin alma yetkileri, 10.10.2026 (D-189). Yeni izinler bir kez eklenir ve
 * gorev rollerine YALNIZ EKLENEREK verilir (givePermissionTo; D-165: ekrandan
 * verilmis / alinmis yetkiye dokunulmaz, syncPermissions yok):
 *
 * - Satin Alma: katalog, siparis kalemleri (teklif, onerilen teklif, siparis,
 *   tamamlama), sirket ici alimlar.
 * - Proje: siparis kalemlerini gorur ve teklif maliyetinden gelen kaleme proje
 *   onayi verir ("proje onay vermeden satin alima dusmeyecek").
 * - Teklif ve Is Gelistirme: katalogu gorur (maliyet kalemleri teklif yetkisiyle).
 * - Yonetim ve Denetci: hepsini gorur.
 *
 * Departman ihtiyac talebini her personel acar (politika; izin gerekmez).
 */
final class ProcurementPermissions20261010Seeder extends ProtectedSeeder
{
    private const READ = ['ViewAny', 'View'];

    private const WRITE = ['ViewAny', 'View', 'Create', 'Update'];

    /** @var array<string, list<string>> Konu => yetenekler (olusturulacak izinler). */
    private const PERMISSIONS = [
        'CatalogItem' => self::WRITE,
        'PurchaseNeed' => [...self::WRITE, 'ApproveProject'],
        'CompanyPurchase' => self::WRITE,
    ];

    /**
     * Birim kodu => konu => yetenekler.
     *
     * @return array<string, array<string, list<string>>>
     */
    private static function grants(): array
    {
        return [
            'SATIN_ALMA' => [
                'CatalogItem' => self::WRITE,
                'PurchaseNeed' => self::WRITE,
                'CompanyPurchase' => self::WRITE,
            ],
            'PROJE' => [
                'CatalogItem' => self::READ,
                'PurchaseNeed' => [...self::READ, 'ApproveProject'],
            ],
            'TEKLIF' => ['CatalogItem' => self::READ],
            'IS_GELISTIRME' => ['CatalogItem' => self::READ],
            'YONETIM' => [
                'CatalogItem' => self::READ,
                'PurchaseNeed' => self::READ,
                'CompanyPurchase' => self::READ,
            ],
        ];
    }

    public function run(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('purchase_needs')) {
            $this->command?->warn('Izin tablosu ya da B52 yok; satin alma yetkileri atlandi.');

            return;
        }

        foreach (self::PERMISSIONS as $subject => $abilities) {
            foreach ($abilities as $ability) {
                $name = $ability.':'.$subject;
                $this->row('permission:'.$name, static fn (): Permission => Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $grants = self::grants();
        $given = 0;

        foreach (Position::query()->with(['orgUnit', 'role'])->get() as $position) {
            $unitCode = (string) ($position->orgUnit?->code ?? '');

            if (! isset($grants[$unitCode])) {
                continue;
            }

            $this->row('position-grant:'.$unitCode.'|'.(string) $position->code, function () use ($position, $grants, $unitCode, &$given): ?Role {
                $role = $position->role;

                if (! $role instanceof Role) {
                    return null;
                }

                $role->givePermissionTo(self::names($grants[$unitCode]));
                $given++;

                return $role;
            });
        }

        $this->row('role-grant:'.RoleResolver::AUDITOR, function (): ?Role {
            $role = Role::query()->where('name', RoleResolver::AUDITOR)->where('guard_name', 'web')->first();

            if (! $role instanceof Role) {
                return null;
            }

            $role->givePermissionTo(self::names(['CatalogItem' => self::READ, 'PurchaseNeed' => self::READ, 'CompanyPurchase' => self::READ]));

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->command?->info(sprintf('Satin alma yetkileri: %d gorev rolune eklendi.', $given));
    }

    /**
     * @param  array<string, list<string>>  $matrix
     * @return list<string>
     */
    private static function names(array $matrix): array
    {
        $names = [];

        foreach ($matrix as $subject => $abilities) {
            foreach ($abilities as $ability) {
                $names[] = $ability.':'.$subject;
            }
        }

        return array_values(array_unique($names));
    }
}
