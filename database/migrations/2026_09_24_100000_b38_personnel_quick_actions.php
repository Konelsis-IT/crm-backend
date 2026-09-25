<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B38 - Kisisel hizli islemler (D-122, 24 Eylul 2026 kullanici karari:
 * "Kisi isterse kendisine gore hizli islemler butonunda neler gorulmesi
 * gerektigini degistirebilecek. Sabit zorunlu olanlar eklenmis olabilir.").
 *
 * Hizli islemlerin kendisi kodda tanimlidir (App\Support\QuickActions\
 * QuickActionCatalog); bu tablo yalniz kisinin sectigi islemlerin kodunu ve
 * sirasini tutar. Sabit islemler buraya yazilmaz. Katalogdan kalkan bir kod
 * ekranda yok sayilir, satir silinmez.
 *
 * On kosul: B01 (personnel).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('personnel_quick_actions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('personnel_id');
            $table->string('action_code', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->auditCreated($table);

            $table->unique(['personnel_id', 'action_code'], 'uk_personnel_quick_actions_code');
            $table->index(['personnel_id', 'sort_order'], 'ix_personnel_quick_actions_order');
            $table->foreign('personnel_id', 'fk_personnel_quick_actions_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->personnelForeignKeys('personnel_quick_actions');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('personnel_quick_actions', function (Blueprint $table): void {
            $table->dropForeign($this->fkName('personnel_quick_actions', 'created_by_personnel_id'));
            $table->dropForeign('fk_personnel_quick_actions_personnel');
        });

        Schema::dropIfExists('personnel_quick_actions');
    }
};
