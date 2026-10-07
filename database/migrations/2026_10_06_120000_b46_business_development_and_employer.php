<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B46 - Is gelistirme duzeni ve Isveren rolu (D-167, 6 Ekim 2026 kullanici
 * talimatlari).
 *
 * - `business_cases.license_status`: besinci "Proje durumu" YEKA ("Proje
 *   durumunda ... 5. olarak YEKA durumu eklenecek").
 * - `business_cases.heat_score`: varsayilan 0; bos olan butun kayitlar 0
 *   ("Default 0 olsun ki arayuzde sicaklik alani gorulsun"). Sicaklik 0 ise
 *   kayit Yatirimci projesi, 0'dan buyukse Potansiyel istir.
 * - `party_roles`: Musteri, Isveren ve Yatirimci tek rol Isveren olur ("3'u
 *   de ayni anlami tasiyor, 3'une de isveren ismini ver, tek isimde topla").
 *   Bir tarafin acik Musteri / Yatirimci rolu Isveren'e cevrilir; tarafta
 *   zaten acik Isveren varsa ikinci rol kapatilir (gecmisi kalir). Kapanmis
 *   eski satirlar da Isveren olarak yazilir. Kontrol kisiti eski kodlari
 *   kabul etmeye devam eder; uygulama yeni rolu her zaman Isveren yazar.
 *
 * On kosul: B43 (license_status, heat_score), B16 (party_roles).
 */
return new class extends KonelsisMigration
{
    private const LICENSE_PREVIOUS = ['unlicensed_5_1_c', 'unlicensed_5_1_h', 'pre_license', 'license'];

    public function up(): void
    {
        $this->dropCheck('business_cases', 'ck_business_cases_license_status_enum');
        $this->enumCheck('business_cases', 'license_status', [...self::LICENSE_PREVIOUS, 'yeka']);

        DB::table('business_cases')->whereNull('heat_score')->update(['heat_score' => 0]);

        Schema::table('business_cases', function (Blueprint $table): void {
            $table->unsignedTinyInteger('heat_score')->nullable()->default(0)->change();
        });

        $this->mergeEmployerRoles();
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('business_cases', function (Blueprint $table): void {
            $table->unsignedTinyInteger('heat_score')->nullable()->default(null)->change();
        });

        $this->dropCheck('business_cases', 'ck_business_cases_license_status_enum');
        $this->enumCheck('business_cases', 'license_status', self::LICENSE_PREVIOUS);
        // Rol birlestirmesi geri alinmaz (hangi rolun Musteri ya da Yatirimci oldugu tutulmadi).
    }

    private function mergeEmployerRoles(): void
    {
        $open = DB::table('party_roles')
            ->whereIn('role_code', ['employer', 'customer', 'investor'])
            ->whereNull('valid_until')
            ->orderByRaw("CASE WHEN `role_code` = 'employer' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get(['id', 'party_id', 'role_code']);

        $kept = [];

        foreach ($open as $row) {
            if (! isset($kept[$row->party_id])) {
                $kept[$row->party_id] = true;

                if ($row->role_code !== 'employer') {
                    DB::table('party_roles')->where('id', $row->id)->update(['role_code' => 'employer']);
                }

                continue;
            }

            // Tarafta zaten acik Isveren var: ikinci rol kapanir, gecmisi kalir.
            DB::table('party_roles')->where('id', $row->id)->update([
                'role_code' => 'employer',
                'status' => 'ended',
                'valid_until' => DB::raw('GREATEST(UTC_TIMESTAMP(6), DATE_ADD(`valid_from`, INTERVAL 1 SECOND))'),
            ]);
        }

        DB::table('party_roles')
            ->whereIn('role_code', ['customer', 'investor'])
            ->whereNotNull('valid_until')
            ->update(['role_code' => 'employer']);
    }

    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $this->shorten($name)));
    }
};
