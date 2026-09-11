<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B03A - Pozisyon <-> rol bagi (kullanici karari, 11 Eylul 2026, D-81):
 * "Tum pozisyonlar icin ayri yetki olmalidir."
 *
 * Her pozisyonun kendi Shield rolu vardir; `positions.role_id` o rolu
 * gosterir. Rol, pozisyon olusturulunca/adi degisince servis tarafindan
 * uretilir ve pozisyona atanan personele otomatik verilir/alinir
 * (App\Services\Authorization\PositionRoleSync). Rolun hangi ekranlara
 * yetkili oldugu Ayarlar > Roller ekraninda isaretlenir.
 *
 * On kosul: B05 (`roles`) uygulanmis olmali. Uygulanana kadar pozisyon
 * rolleri uretilmez (SchemaReadiness::hasBatch('B03A')).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id')->nullable()->after('title');

            $table->unique('role_id', 'uk_positions_role');
            $table->foreign('role_id', 'fk_positions_role')
                ->references('id')->on('roles')->nullOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('positions', function (Blueprint $table): void {
            $table->dropForeign('fk_positions_role');
            $table->dropUnique('uk_positions_role');
            $table->dropColumn('role_id');
        });
    }
};
