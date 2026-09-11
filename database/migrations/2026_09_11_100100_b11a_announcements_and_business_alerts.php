<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B11A - Bildirim sistemi, ilk dilim (kullanici karari, 11 Eylul 2026, D-82):
 * "Roller arasinda bildirim; amir ekibine bildirim; genel duyuru; acil
 * durumlarda yaklasan tarihlerde bildirim; bildirimden onaylama."
 *
 * B11'in tam kural motoru (notification_rules/…/preferences, 07 SS4) bu
 * turda kurulmadi; teslimat kanali Filament'in kendi `notifications`
 * tablosudur (D-49). Bu batch iki kayit tablosu ekler:
 *
 * - `announcements`: kisi/rol/departman/ekip/herkes hedefli gonderimlerin
 *   kaydi (kim, kime, ne, ne zaman, kac aliciya). Yalniz eklenir.
 * - `business_alerts`: 07 SS5.4'un alt kumesi — yaklasan/gecmis son tarih
 *   uyarilari (`trigger_code` = kaynak, `dedupe_key` ile ayni konu/seviye
 *   bir kez acilir). `owner_personnel_id` kanonik tasarimda zorunludur;
 *   sahibi olmayan kayitlar (ihale son tarihi gibi) icin burada NULL
 *   birakildi ve bildirim yoneticilere gider. `business_alert_acknowledgements`
 *   ve `_resolutions` tablolari yerine ilk dilimde `acknowledged_*` kolonlari
 *   yeterli goruldu; tam B11 gelince ayni tablo genisletilir.
 *
 * On kosul: B02 (personnel), B03 (org_units), B17 (projects).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sender_personnel_id')->nullable();
            $this->status($table, 'audience_kind');
            $table->unsignedBigInteger('audience_id')->nullable();
            $table->json('audience_ids')->nullable();
            $table->string('audience_label', 200);
            $table->string('title', 200);
            $table->text('body');
            $this->status($table, 'priority')->default('normal');
            $table->text('action_url')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $this->ts($table, 'sent_at');
            $this->auditCreated($table);

            $table->index('sent_at', 'ix_announcements_sent');
            $table->index(['sender_personnel_id', 'sent_at'], 'ix_announcements_sender');
            $table->foreign('sender_personnel_id', 'fk_announcements_sender')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('announcements', 'audience_kind', ['team', 'department', 'role', 'personnel', 'all']);
        $this->enumCheck('announcements', 'priority', ['normal', 'important', 'urgent']);
        $this->personnelForeignKeys('announcements');

        Schema::create('business_alerts', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'alert_no', 32);
            $this->code($table, 'trigger_code', 64);
            $this->code($table, 'subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('org_unit_id')->nullable();
            $this->status($table, 'severity')->default('warning');
            $table->unsignedBigInteger('owner_personnel_id')->nullable();
            $table->string('title_key', 120);
            $table->json('params')->nullable();
            $table->text('url')->nullable();
            $this->ts($table, 'due_at')->nullable();
            $this->status($table, 'state')->default('open');
            $this->ts($table, 'opened_at');
            $this->ts($table, 'acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by_personnel_id')->nullable();
            $this->ts($table, 'closed_at')->nullable();
            $this->ascii($table, 'dedupe_key', 96);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('alert_no', 'uk_business_alerts_no');
            $table->unique('dedupe_key', 'uk_business_alerts_dedupe');
            $table->index(['state', 'owner_personnel_id'], 'ix_business_alerts_state_owner');
            $table->index(['subject_type', 'subject_id'], 'ix_business_alerts_subject');
            $table->index(['trigger_code', 'state'], 'ix_business_alerts_trigger_state');

            $table->foreign('project_id', 'fk_business_alerts_project')
                ->references('id')->on('projects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('org_unit_id', 'fk_business_alerts_org_unit')
                ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_personnel_id', 'fk_business_alerts_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('acknowledged_by_personnel_id', 'fk_business_alerts_acknowledger')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('business_alerts', 'severity', ['warning', 'high', 'critical']);
        $this->enumCheck('business_alerts', 'state', ['open', 'acknowledged', 'resolved', 'closed', 'cancelled']);
        $this->personnelForeignKeys('business_alerts');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('business_alerts', function (Blueprint $blueprint): void {
            foreach (['created_by_personnel_id', 'updated_by_personnel_id'] as $column) {
                $blueprint->dropForeign($this->fkName('business_alerts', $column));
            }
        });
        Schema::dropIfExists('business_alerts');

        Schema::table('announcements', function (Blueprint $blueprint): void {
            $blueprint->dropForeign($this->fkName('announcements', 'created_by_personnel_id'));
        });
        Schema::dropIfExists('announcements');
    }
};
