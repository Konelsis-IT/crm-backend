<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B08 - Personel Hareketleri ve kontrollu kayit turu listesi.
 *
 * personnel_activities yalniz eklenir: calisma zamani veritabani rolune
 * UPDATE/DELETE verilmez. Durum degisikligi ayri bir tabloda tutulmaz;
 * gecmisin tek kaynagi Personel Hareketleri'dir (kullanici karari D-47).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('reference_types', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'target_type', 32);
            $this->code($table, 'table_name');
            $this->code($table, 'label_key');
            $table->string('owning_domain', 32);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('target_type', 'uk_reference_types_target_type');
        });
        $this->enumCheck('reference_types', 'status', ['active', 'retired']);

        Schema::create('reference_type_usages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('reference_type_id');
            $this->status($table, 'usage_context');
            $this->auditCreated($table);

            $table->unique(['reference_type_id', 'usage_context'], 'uk_reference_type_usages_pair');
            $table->foreign('reference_type_id', 'fk_reference_type_usages_type')
                ->references('id')->on('reference_types')->cascadeOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('reference_type_usages', 'usage_context', [
            'document_link', 'message_link', 'email_link', 'activity',
            'workflow_subject', 'approval_subject', 'notification_subject', 'task_context',
            'generated_output_source', 'report_schedule_target', 'inventory_source', 'project_change_source',
        ]);

        Schema::create('personnel_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('personnel_id')->nullable();
            $table->string('subject_type', 32);
            $table->string('subject_id', 64);
            $this->code($table, 'action_code');
            $this->status($table, 'channel')->default('panel');
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $this->ts($table, 'occurred_at');

            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'ix_personnel_activities_subject');
            $table->index(['personnel_id', 'occurred_at'], 'ix_personnel_activities_personnel');
            $table->index(['action_code', 'occurred_at'], 'ix_personnel_activities_action');
            $table->foreign('personnel_id', 'fk_personnel_activities_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('personnel_activities', 'channel', ['panel', 'scheduled', 'integration', 'import']);

        foreach (['reference_types', 'reference_type_usages'] as $table) {
            $this->personnelForeignKeys($table);
        }
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::dropIfExists('personnel_activities');
        Schema::dropIfExists('reference_type_usages');
        Schema::dropIfExists('reference_types');
    }
};
