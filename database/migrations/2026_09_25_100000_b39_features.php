<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B39 - Ozellik anahtarlari (D-128, 25 Eylul 2026 kullanici karari:
 * "Gelistirilen tum ozellikleri yazdigimiz, ilgili ozelligin projede aktif
 * pasif ayarinin sadece db'de oldugu bir gelistirme yapalim. Bu gelistirme
 * ile ben db ustunden istedigim ozelligi acicam istemedigimi kapaticam.").
 *
 * Her satir bir ozelliktir; `is_active` yalniz veritabanindan degistirilir
 * (arayuzde ve .env'de ayari yoktur). Satirlari uygulama kendisi yazar:
 * ozellik katalogu (App\Enums\Platform\Feature) ilk istekte tabloyla
 * esitlenir, yeni ozellik varsayilan durumuyla eklenir, mevcut satirin
 * `is_active` degerine dokunulmaz. Ust ozellik kapaliysa alt ozellikler de
 * kapali sayilir.
 *
 * On kosul: B00 (personnel).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 64);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 150);
            $table->string('description', 1000)->nullable();
            $this->code($table, 'decision_ref', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_features_code');
            $table->index('parent_id', 'ix_features_parent');
            $table->foreign('parent_id', 'fk_features_parent')
                ->references('id')->on('features')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->personnelForeignKeys('features');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('features', function (Blueprint $table): void {
            $table->dropForeign($this->fkName('features', 'created_by_personnel_id'));
            $table->dropForeign($this->fkName('features', 'updated_by_personnel_id'));
            $table->dropForeign('fk_features_parent');
        });

        Schema::dropIfExists('features');
    }
};
