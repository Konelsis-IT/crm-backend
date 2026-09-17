<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B30 - HES kapsaminin kendi tutar alani (D-101 devami, 16 Eylul 2026
 * kullanici talimati).
 *
 * HES icin "Maliyet" ve "Satis" GES'in zaten var olan `cost_amount` /
 * `sales_amount` kolonlarini paylasir (ayni anlam, ayri satir: her kapsam
 * satirinin kendi `scope_type`'i vardir, karismaz). Yalniz HES'e ozgu ucuncu
 * kalem icin yeni kolon gerekir: `hes_unit_cost` ("Jeneratör/Türbin başı
 * maliyet"), DECIMAL(18,2), diger tutar kolonlariyla ayni olcekte.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::table('business_case_scopes', function (Blueprint $table): void {
            $table->decimal('hes_unit_cost', 18, 2)->nullable()->after('tm_feeder_cost');
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('business_case_scopes', function (Blueprint $table): void {
            $table->dropColumn('hes_unit_cost');
        });
    }
};
