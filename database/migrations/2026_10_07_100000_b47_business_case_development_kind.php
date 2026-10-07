<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B47 - Is gelistirme turunun elle secilmesi (D-170, 7 Ekim 2026 kullanici
 * talimati: listeden gelen potansiyel isler sicakliktan bagimsiz "Potansiyel
 * is", firma listesindeki yatirimci projeleri "Yatirimci projesi" olmali).
 *
 * `business_cases.development_kind` (bos olabilir, CHECK: investor_project /
 * potential_job). Bos ise tur sicakliktan bulunur (D-167: 0 ya da bos =
 * Yatirimci projesi, 0'dan buyuk = Potansiyel is); dolu ise kayitli tur
 * gecerlidir. Tur yalniz Is Gelistirme asamasinda anlamlidir (uygulama kurali,
 * BusinessCase::developmentKind). Veri tasima yoktur: var olan kayitlar bos
 * kalir ve bugunku gibi sicakliga gore gorunur.
 *
 * On kosul: B16 (business_cases), B43 (heat_score).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        if (Schema::hasColumn('business_cases', 'development_kind')) {
            return;
        }

        Schema::table('business_cases', function (Blueprint $table): void {
            $this->status($table, 'development_kind')->nullable()->after('heat_score');
        });

        $this->enumCheck('business_cases', 'development_kind', ['investor_project', 'potential_job']);
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if ($this->isMySql()) {
            DB::statement(sprintf(
                'ALTER TABLE `business_cases` DROP CHECK `%s`',
                $this->shorten('ck_business_cases_development_kind_enum'),
            ));
        }

        Schema::table('business_cases', function (Blueprint $table): void {
            $table->dropColumn('development_kind');
        });
    }
};
