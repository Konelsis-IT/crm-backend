<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B00 — Laravel cache tables (framework). Production uses Redis; these
 * tables remain as the non-Redis fallback (docs/planning/12 §7).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
