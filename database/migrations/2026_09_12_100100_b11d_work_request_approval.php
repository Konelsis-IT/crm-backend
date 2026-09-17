<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B11D - Onaya tabi talep (kullanici karari, 12 Eylul 2026, D-87).
 *
 * Talep ucuncu bir asama kazanir: talep eden -> talep edilen -> onaylayan.
 * Talep acilirken "onaya tabi" isaretlenirse onay mercii (personel) secilir;
 * muhatap isi tamamlayinca talep `awaiting_approval` durumuna gecer ve onay
 * motoru (B07) secilen kisi icin tek adimli onay talebi acar. Onaylaninca
 * talep kapanir, reddedilirse muhataba geri doner.
 *
 *  - `work_requests.requires_approval`, `approver_personnel_id`, `approval_request_id`
 *  - `work_requests.status` CHECK'ine `awaiting_approval`
 *  - `approval_steps.resolver_type` CHECK'ine `designated_approver`
 *    (konu kaydinda belirlenen onay mercii; her konu turu kullanabilir)
 *
 * On kosul: B11B. `approval_requests` (B07) uygulanmadiysa FK'siz kalir.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::table('work_requests', function (Blueprint $table): void {
            $table->boolean('requires_approval')->default(false)->after('priority');
            $table->unsignedBigInteger('approver_personnel_id')->nullable()->after('assignee_personnel_id');
            $table->unsignedBigInteger('approval_request_id')->nullable()->after('approver_personnel_id');

            $table->index(['status', 'approver_personnel_id'], 'ix_work_requests_status_approver');
            $table->foreign('approver_personnel_id', 'fk_work_requests_approver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });

        if (Schema::hasTable('approval_requests')) {
            Schema::table('work_requests', function (Blueprint $table): void {
                $table->foreign('approval_request_id', 'fk_work_requests_approval_request')
                    ->references('id')->on('approval_requests')->nullOnDelete()->restrictOnUpdate();
            });
        }

        $this->dropCheck('work_requests', 'ck_work_requests_status_enum');
        $this->enumCheck('work_requests', 'status', ['open', 'in_progress', 'awaiting_approval', 'done', 'rejected', 'cancelled']);
        $this->check(
            'work_requests',
            'ck_work_requests_approver',
            '`requires_approval` = 0 OR `approver_personnel_id` IS NOT NULL',
        );

        if (Schema::hasTable('approval_steps')) {
            $this->dropCheck('approval_steps', 'ck_approval_steps_resolver_type_enum');
            $this->enumCheck('approval_steps', 'resolver_type', [
                'personnel', 'position', 'line_manager', 'org_unit_manager', 'functional_manager',
                'project_role', 'functional_area_role', 'executive', 'rbac_role', 'designated_approver',
            ]);
        }
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (Schema::hasTable('approval_steps')) {
            $this->dropCheck('approval_steps', 'ck_approval_steps_resolver_type_enum');
            $this->enumCheck('approval_steps', 'resolver_type', [
                'personnel', 'position', 'line_manager', 'org_unit_manager', 'functional_manager',
                'project_role', 'functional_area_role', 'executive', 'rbac_role',
            ]);
        }

        $this->dropCheck('work_requests', 'ck_work_requests_approver');
        $this->dropCheck('work_requests', 'ck_work_requests_status_enum');
        $this->enumCheck('work_requests', 'status', ['open', 'in_progress', 'done', 'rejected', 'cancelled']);

        Schema::table('work_requests', function (Blueprint $table): void {
            if (Schema::hasTable('approval_requests')) {
                $table->dropForeign('fk_work_requests_approval_request');
            }

            $table->dropForeign('fk_work_requests_approver');
            $table->dropIndex('ix_work_requests_status_approver');
            $table->dropColumn(['requires_approval', 'approver_personnel_id', 'approval_request_id']);
        });
    }

    /** Adlandirilmis CHECK'i kaldirir (yalniz MySQL). */
    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $this->shorten($name)));
    }
};
