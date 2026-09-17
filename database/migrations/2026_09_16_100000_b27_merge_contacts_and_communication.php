<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B27 - Kisi ve iletisim bilgisinin tek ekranda birlesmesi (D-94, 16 Eylul
 * 2026 kullanici karari: "Bir kisi ve ona ait tum iletisim bilgilerini
 * ekliyoruz").
 *
 * Onceki yapida bir firmanin yetkilisini yazmak uc adim gerektiriyordu:
 * kisi icin ayri bir taraf kaydi ac, onu `contact_relationships` ile firmaya
 * bagla, telefonunu ayrica firmanin `communication_points` listesine yaz.
 * Iki liste birbirinden kopuktu: bir telefonun kime ait oldugu belli degildi.
 *
 * Bu grupla:
 *  - `contact_relationships.contact_name` : kisi adi dogrudan satira yazilir.
 *  - `contact_relationships.contact_party_id` NULL edilebilir; kisi icin ayri
 *    taraf kaydi zorunlu degildir (yalniz kayitli bir taraf ise baglanir).
 *  - `communication_points.contact_relationship_id` : bir iletisim kanali
 *    ARTIK BIR KISIYE ait olabilir. Bos ise kanal kurumun kendisinindir
 *    (santral, genel e-posta).
 *
 * Boylece tek ekranda kisi eklenir ve altina o kisiye ait butun kanallar
 * (is telefonu, cep, e-posta, faks...) sinirsiz sayida yazilir. Tablo
 * dusurulmez; iki taraf da korunur.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::table('contact_relationships', function (Blueprint $table): void {
            $table->string('contact_name', 200)->nullable()->after('contact_party_id');
        });

        // Kisi icin ayri taraf kaydi artik zorunlu degil.
        Schema::table('contact_relationships', function (Blueprint $table): void {
            $table->unsignedBigInteger('contact_party_id')->nullable()->change();
        });

        $this->check(
            'contact_relationships',
            'ck_contact_relationships_identity',
            '`contact_party_id` IS NOT NULL OR `contact_name` IS NOT NULL',
        );

        Schema::table('communication_points', function (Blueprint $table): void {
            $table->unsignedBigInteger('contact_relationship_id')->nullable()->after('party_id');

            $table->index(['contact_relationship_id', 'is_primary'], 'ix_communication_points_contact');
            $table->foreign('contact_relationship_id', 'fk_communication_points_contact')
                ->references('id')->on('contact_relationships')
                ->cascadeOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('communication_points', function (Blueprint $table): void {
            $table->dropForeign('fk_communication_points_contact');
            $table->dropIndex('ix_communication_points_contact');
            $table->dropColumn('contact_relationship_id');
        });

        $this->dropCheck('contact_relationships', 'ck_contact_relationships_identity');

        Schema::table('contact_relationships', function (Blueprint $table): void {
            $table->dropColumn('contact_name');
        });

        Schema::table('contact_relationships', function (Blueprint $table): void {
            $table->unsignedBigInteger('contact_party_id')->nullable(false)->change();
        });
    }

    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $name));
    }
};
