<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B13A - Sertifika tanimi gecerlilik tarihi (kullanici karari, 10 Eylul 2026,
 * D-80): "Geçerlilik (ay)" girdisi tarih olur.
 *
 * `validity_months` kolonu kaldirilmaz (B13 ile uyumlu, bos kalir);
 * `valid_until` DATE NULL eklenir. Uygulanana kadar form eski ay alanini
 * gosterir (SchemaReadiness::hasBatch('B13A')).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::table('certifications', function (Blueprint $table): void {
            $table->date('valid_until')->nullable()->after('validity_months');
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('certifications', function (Blueprint $table): void {
            $table->dropColumn('valid_until');
        });
    }
};
