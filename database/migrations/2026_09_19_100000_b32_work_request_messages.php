<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B32 - Talep yazismasi (D-108, kullanici karari, 19 Eylul 2026).
 *
 * Talep ilk mesajdir; cevaplar `work_request_messages` satirlaridir. Sohbet
 * (B12A) ile karistirilmaz: cevrimici durumu, yaziyor bilgisi ve okundu
 * imleci yoktur. Yazisma talebin taraflari (kimden, kime, sorumlu, onay
 * mercii) arasindadir. `message_kind`: `text` (cevap) ya da `forward`
 * (yonlendirme satiri; gerekce `body`da). Ek dosyalar `file_objects` uzerinden
 * tutulur; baglanti adresleri metnin icindedir.
 *
 * On kosul: B11B, B06.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('work_request_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('work_request_id');
            $table->unsignedBigInteger('author_personnel_id');
            $this->status($table, 'message_kind')->default('text');
            $table->text('body')->nullable();
            $this->auditCreated($table);

            $table->index(['work_request_id', 'id'], 'ix_wr_messages_request');
            $table->foreign('work_request_id', 'fk_wr_messages_request')
                ->references('id')->on('work_requests')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('author_personnel_id', 'fk_wr_messages_author')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });

        $this->enumCheck('work_request_messages', 'message_kind', ['text', 'forward', 'initial']);
        $this->personnelForeignKeys('work_request_messages');

        Schema::create('work_request_message_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('file_object_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->auditCreated($table);

            $table->index('message_id', 'ix_wr_message_files_message');
            $table->foreign('message_id', 'fk_wr_message_files_message')
                ->references('id')->on('work_request_messages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('file_object_id', 'fk_wr_message_files_file')
                ->references('id')->on('file_objects')->restrictOnDelete()->restrictOnUpdate();
        });

        $this->personnelForeignKeys('work_request_message_files');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        foreach (['work_request_message_files', 'work_request_messages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropForeign($this->fkName($table, 'created_by_personnel_id'));
            });
        }

        Schema::dropIfExists('work_request_message_files');
        Schema::dropIfExists('work_request_messages');
    }
};
