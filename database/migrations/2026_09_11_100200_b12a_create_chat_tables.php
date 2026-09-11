<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B12A - Kurum ici sohbet (kullanici karari, 11 Eylul 2026, D-83): 08 SS2'nin
 * (B12 "Iletisim") sohbet dilimi. E-posta alimi (08 SS3) bu turda yok.
 *
 * - `conversations`: birebir (`direct`, `direct_pair_key` ile tek) ve grup.
 * - `conversation_memberships`: uyelik; `pinned_at` (sabitleme) ve
 *   `history_visible_from` (tek tarafli sohbet silme: bu andan onceki
 *   mesajlar o kisiye gorunmez) kullanici istekleriyle eklendi.
 * - `messages`: konusma icinde monoton `conversation_sequence`; tur
 *   text | file_share | link_share | document_share | system. Kanonik
 *   `message_versions` (duzenleme gecmisi) ve `message_mentions` bu dilimde
 *   yok — govde dogrudan `body`, baglanti `link_url`.
 * - `message_attachments`: dosya (`file_object_id`) XOR kontrollu dokuman
 *   (`document_revision_id`).
 * - `message_hides`: tek tarafli mesaj silme (kanonik tasarima ek).
 * - `conversation_read_cursors`: kisi basina son okunan sira.
 *
 * Cevrimici / yaziyor bilgisi tabloya yazilmaz (onbellek). On kosul: B01
 * (security_classifications), B02 (personnel), B06 (file_objects,
 * document_revisions).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $this->status($table, 'conversation_type');
            $this->status($table, 'scope_type')->default('none');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('title', 160)->nullable();
            $table->unsignedBigInteger('classification_id');
            $this->status($table, 'history_policy')->default('full_history');
            $this->status($table)->default('active');
            $table->unsignedBigInteger('last_message_sequence')->default(0);
            $this->ts($table, 'last_message_at')->nullable();
            $this->ascii($table, 'direct_pair_key', 64)->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('direct_pair_key', 'uk_conversations_direct_pair');
            $table->index(['status', 'last_message_at'], 'ix_conversations_status_last');
            $table->foreign('classification_id', 'fk_conversations_classification')
                ->references('id')->on('security_classifications')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('conversations', 'conversation_type', ['company', 'functional_area', 'department', 'project', 'group', 'direct']);
        $this->enumCheck('conversations', 'scope_type', ['none', 'org_unit', 'project', 'functional_area', 'team']);
        $this->enumCheck('conversations', 'history_policy', ['full_history', 'from_join', 'none']);
        $this->enumCheck('conversations', 'status', ['active', 'archived', 'locked']);
        $this->personnelForeignKeys('conversations');

        Schema::create('conversation_memberships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('personnel_id');
            $this->status($table, 'role')->default('member');
            $this->ts($table, 'joined_at');
            $this->ts($table, 'left_at')->nullable();
            $this->ts($table, 'history_visible_from')->nullable();
            $table->boolean('is_muted')->default(false);
            $this->ts($table, 'pinned_at')->nullable();

            if ($this->isMySql()) {
                $table->unsignedTinyInteger('active_guard')
                    ->storedAs('CASE WHEN `left_at` IS NULL THEN 1 END')
                    ->nullable();
            } else {
                $table->unsignedTinyInteger('active_guard')->nullable();
            }

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['conversation_id', 'personnel_id', 'active_guard'], 'uk_conversation_memberships_active');
            $table->index(['personnel_id', 'left_at', 'pinned_at'], 'ix_conversation_memberships_personnel');
            $table->foreign('conversation_id', 'fk_conversation_memberships_conversation')
                ->references('id')->on('conversations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_conversation_memberships_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('conversation_memberships', 'role', ['owner', 'admin', 'member', 'observer']);
        $this->personnelForeignKeys('conversation_memberships');

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('conversation_sequence');
            $table->unsignedBigInteger('author_personnel_id');
            $table->unsignedBigInteger('reply_to_message_id')->nullable();
            $this->status($table, 'message_kind')->default('text');
            $table->text('body')->nullable();
            $table->text('link_url')->nullable();
            $this->status($table)->default('sent');
            $this->ts($table, 'sent_at');
            $this->ts($table, 'redacted_at')->nullable();
            $table->unsignedBigInteger('redacted_by_personnel_id')->nullable();
            $table->string('redaction_reason', 120)->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['conversation_id', 'conversation_sequence'], 'uk_messages_sequence');
            $table->index(['conversation_id', 'sent_at'], 'ix_messages_conversation_sent');
            $table->foreign('conversation_id', 'fk_messages_conversation')
                ->references('id')->on('conversations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('author_personnel_id', 'fk_messages_author')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('reply_to_message_id', 'fk_messages_reply_to')
                ->references('id')->on('messages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('redacted_by_personnel_id', 'fk_messages_redactor')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('messages', 'message_kind', ['text', 'system', 'file_share', 'link_share', 'document_share']);
        $this->enumCheck('messages', 'status', ['sent', 'edited', 'redacted', 'removed_by_policy']);
        $this->personnelForeignKeys('messages');

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('file_object_id')->nullable();
            $table->unsignedBigInteger('document_revision_id')->nullable();
            $table->string('caption', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->auditCreated($table);

            $table->index('message_id', 'ix_message_attachments_message');
            $table->foreign('message_id', 'fk_message_attachments_message')
                ->references('id')->on('messages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('file_object_id', 'fk_message_attachments_file')
                ->references('id')->on('file_objects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_message_attachments_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->check('message_attachments', 'ck_message_attachments_target_xor', '(`file_object_id` IS NULL) <> (`document_revision_id` IS NULL)');
        $this->personnelForeignKeys('message_attachments');

        Schema::create('message_hides', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('personnel_id');
            $this->ts($table, 'hidden_at');

            $table->unique(['message_id', 'personnel_id'], 'uk_message_hides_message_personnel');
            $table->foreign('message_id', 'fk_message_hides_message')
                ->references('id')->on('messages')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_message_hides_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('conversation_read_cursors', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('last_read_sequence')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['conversation_id', 'personnel_id'], 'uk_conversation_read_cursors');
            $table->foreign('conversation_id', 'fk_conversation_read_cursors_conversation')
                ->references('id')->on('conversations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_conversation_read_cursors_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->personnelForeignKeys('conversation_read_cursors');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        foreach ([
            'conversation_read_cursors' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'message_hides' => [],
            'message_attachments' => ['created_by_personnel_id'],
            'messages' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'conversation_memberships' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'conversations' => ['created_by_personnel_id', 'updated_by_personnel_id'],
        ] as $tableName => $auditColumns) {
            if ($auditColumns !== []) {
                Schema::table($tableName, function (Blueprint $blueprint) use ($tableName, $auditColumns): void {
                    foreach ($auditColumns as $column) {
                        $blueprint->dropForeign($this->fkName($tableName, $column));
                    }
                });
            }

            Schema::dropIfExists($tableName);
        }
    }
};
