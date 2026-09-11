<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B07 - Ortak onay motoru (docs/planning/12 SS2.1-2.7, 06 SS4.11; karar D-76).
 *
 * Kullanici talimati (10 Eylul 2026): "Onay motoru (maker-checker/escalation)
 * sistemini de simdi gelistirelim." M03'te disarida birakilan onay altyapisi
 * kuruldu:
 *
 * - `approval_policies` / `approval_policy_versions` / `approval_steps`:
 *   yayimlanan surum degismez; kok tablo `current_version_id` ile guncel
 *   surumu gosterir (composite FK, surum tablosu kurulduktan sonra ALTER).
 * - `approval_requests` / `approval_request_steps` / `approval_decisions`:
 *   talep, adim basina onayci satiri ve kararlar. `active_guard` ayni konu
 *   icin ikinci acik talebi engeller. Karar aninda `subject_hash` yeniden
 *   dogrulanir (14 SS3 madde 7).
 * - `delegations` (06 SS4.11) ve `delegation_snapshots`: vekaletle verilen
 *   karar, karar aninda vekaletin kopyasini tasir.
 *
 * Ertelenen: workflow tablolari (12 SS1; tuketicisi yok) ve
 * `approval_requests.workflow_task_id` FK'si; `escalation_notification_rule_id`
 * (B08 `notification_rules` gelince FK eklenir). DMS'in ertelenmis
 * `document_revisions.approval_request_id` ve `document_reviews.approval_request_id`
 * kolonlari bu batch'te eklendi; teklif/sozlesme/devir/gate tablolarinin ayni
 * adli kolonlari ilgili tuketici baglandiginda eklenir.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const AUDITED_TABLES = [
        'approval_policies', 'approval_policy_versions', 'approval_steps', 'approval_requests',
        'approval_request_steps', 'approval_decisions', 'delegations', 'delegation_snapshots',
    ];

    public function up(): void
    {
        $this->createPolicies();
        $this->createPolicyVersions();
        $this->createSteps();
        $this->createDelegations();
        $this->createRequests();
        $this->createRequestSteps();
        $this->createDecisions();
        $this->createDelegationSnapshots();
        $this->attachDmsColumns();

        foreach (self::AUDITED_TABLES as $table) {
            $this->personnelForeignKeys($table);
        }
    }

    private function createPolicies(): void
    {
        Schema::create('approval_policies', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 64);
            $table->string('name_tr');
            $table->string('name_en');
            $this->code($table, 'subject_type', 32);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $this->status($table)->default('draft');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_approval_policies_code');
            $table->index(['subject_type', 'status'], 'ix_approval_policies_subject_status');
        });
        $this->enumCheck('approval_policies', 'status', ['draft', 'active', 'retired']);
    }

    private function createPolicyVersions(): void
    {
        Schema::create('approval_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('approval_policy_id');
            $table->unsignedInteger('version_no');
            $this->status($table, 'mode')->default('sequential');
            $table->unsignedTinyInteger('quorum_count')->nullable();
            $table->boolean('requires_maker_checker')->default(true);
            $table->boolean('reapproval_on_change')->default(true);
            $table->decimal('applies_min_amount', 20, 4)->nullable();
            $table->decimal('applies_max_amount', 20, 4)->nullable();
            $this->asciiChar($table, 'currency_code', 3)->nullable();
            $this->code($table, 'risk_level', 32)->nullable();
            $table->unsignedInteger('sla_minutes')->nullable();
            // B08 notification_rules gelince FK eklenir (ertelenmis FK deseni).
            $table->unsignedBigInteger('escalation_notification_rule_id')->nullable();
            $this->asciiChar($table, 'definition_hash', 64)->nullable();
            $table->text('change_summary')->nullable();
            $this->status($table)->default('draft');
            $table->unsignedBigInteger('published_by_personnel_id')->nullable();
            $this->ts($table, 'published_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['approval_policy_id', 'version_no'], 'uk_approval_policy_versions_no');
            $table->foreign('approval_policy_id', 'fk_approval_policy_versions_policy')
                ->references('id')->on('approval_policies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_approval_policy_versions_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('published_by_personnel_id', 'fk_approval_policy_versions_publisher')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('approval_policy_versions', 'mode', ['sequential', 'parallel', 'quorum']);
        $this->enumCheck('approval_policy_versions', 'status', ['draft', 'published', 'superseded']);
        $this->enumCheck('approval_policy_versions', 'risk_level', ['normal', 'high', 'crisis']);
        $this->check('approval_policy_versions', 'ck_approval_policy_versions_amounts', '`applies_min_amount` IS NULL OR `applies_max_amount` IS NULL OR `applies_max_amount` >= `applies_min_amount`');
        $this->check('approval_policy_versions', 'ck_approval_policy_versions_quorum', '`mode` <> \'quorum\' OR (`quorum_count` IS NOT NULL AND `quorum_count` >= 1)');

        Schema::table('approval_policies', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'fk_approval_policies_current_version')
                ->references('id')->on('approval_policy_versions')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    private function createSteps(): void
    {
        Schema::create('approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('approval_policy_version_id');
            $this->code($table, 'step_code', 32);
            $table->string('name_tr');
            $table->string('name_en');
            $table->unsignedSmallInteger('sequence_no');
            $this->status($table, 'resolver_type');
            $table->unsignedBigInteger('resolver_target_id')->nullable();
            $this->code($table, 'role_code', 64)->nullable();
            $this->status($table, 'decision_rule')->default('any_one');
            $table->boolean('is_optional')->default(false);
            $table->boolean('allows_delegation')->default(true);
            $table->unsignedInteger('sla_minutes')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['approval_policy_version_id', 'step_code'], 'uk_approval_steps_code');
            $table->index(['approval_policy_version_id', 'sequence_no'], 'ix_approval_steps_sequence');
            $table->foreign('approval_policy_version_id', 'fk_approval_steps_version')
                ->references('id')->on('approval_policy_versions')->cascadeOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('approval_steps', 'resolver_type', [
            'personnel', 'position', 'line_manager', 'org_unit_manager', 'functional_manager',
            'project_role', 'functional_area_role', 'executive', 'rbac_role',
        ]);
        $this->enumCheck('approval_steps', 'decision_rule', ['any_one', 'all', 'majority']);
    }

    private function createDelegations(): void
    {
        Schema::create('delegations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('grantor_personnel_id');
            $table->unsignedBigInteger('delegate_personnel_id');
            $this->code($table, 'capability_code', 64);
            $this->status($table, 'scope_type')->default('all');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->text('reason');
            $table->unsignedBigInteger('approved_by_personnel_id')->nullable();
            $this->status($table)->default('active');
            $this->ts($table, 'valid_from');
            $this->ts($table, 'valid_until');
            $this->ts($table, 'revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by_personnel_id')->nullable();
            $table->text('revoke_reason')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['delegate_personnel_id', 'status', 'valid_until'], 'ix_delegations_delegate_status');
            $table->index(['grantor_personnel_id', 'status'], 'ix_delegations_grantor_status');
            $table->foreign('grantor_personnel_id', 'fk_delegations_grantor')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('delegate_personnel_id', 'fk_delegations_delegate')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approved_by_personnel_id', 'fk_delegations_approver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('revoked_by_personnel_id', 'fk_delegations_revoker')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('delegations', 'scope_type', ['all', 'org_unit', 'project', 'functional_area', 'approval_policy']);
        $this->enumCheck('delegations', 'status', ['pending', 'active', 'revoked', 'expired']);
        // "Kendine vekalet verilemez" kurali serviste (MySQL 8 AUTO_INCREMENT kolonlu CHECK'i reddeder).
        $this->validRangeCheck('delegations');
    }

    private function createRequests(): void
    {
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('approval_policy_version_id');
            // Workflow motoru (12 SS1) ertelendi; FK o batch'te eklenir.
            $table->unsignedBigInteger('workflow_task_id')->nullable();
            $this->code($table, 'subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('subject_revision_id')->nullable();
            $this->asciiChar($table, 'subject_hash', 64);
            $table->string('subject_label')->nullable();
            $table->decimal('amount', 20, 4)->nullable();
            $this->asciiChar($table, 'currency_code', 3)->nullable();
            $this->ascii($table, 'idempotency_key', 32);
            $table->unsignedBigInteger('personnel_id');
            $table->text('note')->nullable();
            $this->ts($table, 'requested_at');
            $table->unsignedSmallInteger('current_step_sequence')->default(0);
            $this->status($table)->default('pending');
            $this->ts($table, 'decided_at')->nullable();
            $this->code($table, 'invalidation_reason', 32)->nullable();

            if ($this->isMySql()) {
                $table->unsignedTinyInteger('active_guard')
                    ->storedAs("CASE WHEN `status` IN ('pending', 'in_progress') THEN 1 END")
                    ->nullable();
            } else {
                $table->unsignedTinyInteger('active_guard')->nullable();
            }

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('idempotency_key', 'uk_approval_requests_idempotency');
            $table->unique(['subject_type', 'subject_id', 'subject_revision_id', 'active_guard'], 'uk_approval_requests_active_subject');
            $table->index(['subject_type', 'subject_id'], 'ix_approval_requests_subject');
            $table->index(['personnel_id', 'status'], 'ix_approval_requests_requester_status');
            $table->index(['status', 'requested_at'], 'ix_approval_requests_status');
            $table->foreign('approval_policy_version_id', 'fk_approval_requests_version')
                ->references('id')->on('approval_policy_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_approval_requests_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_approval_requests_requester')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('approval_requests', 'status', [
            'pending', 'in_progress', 'approved', 'rejected', 'cancelled', 'expired', 'invalidated',
        ]);
        $this->enumCheck('approval_requests', 'invalidation_reason', ['hash_mismatch', 'subject_withdrawn', 'policy_superseded']);
    }

    private function createRequestSteps(): void
    {
        Schema::create('approval_request_steps', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('approval_request_id');
            $table->unsignedBigInteger('approval_step_id');
            $table->unsignedSmallInteger('sequence_no');
            $table->unsignedBigInteger('personnel_id')->nullable();
            $this->code($table, 'resolved_role_snapshot', 32);
            $this->status($table, 'resolution_status')->default('resolved');
            $this->code($table, 'unresolved_reason', 32)->nullable();
            $this->status($table)->default('waiting');
            $this->ts($table, 'activated_at')->nullable();
            $this->ts($table, 'due_at')->nullable();
            $this->ts($table, 'decided_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['approval_request_id', 'approval_step_id', 'personnel_id'], 'uk_approval_request_steps_person');
            $table->index(['personnel_id', 'status'], 'ix_approval_request_steps_person_status');
            $table->index(['status', 'due_at'], 'ix_approval_request_steps_due');
            $table->foreign('approval_request_id', 'fk_approval_request_steps_request')
                ->references('id')->on('approval_requests')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('approval_step_id', 'fk_approval_request_steps_step')
                ->references('id')->on('approval_steps')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_approval_request_steps_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('approval_request_steps', 'resolution_status', ['resolved', 'unresolved']);
        $this->enumCheck('approval_request_steps', 'unresolved_reason', [
            'vacant_position', 'inactive_user', 'no_manager', 'no_role_holder', 'sod_conflict',
        ]);
        $this->enumCheck('approval_request_steps', 'status', ['waiting', 'active', 'approved', 'rejected', 'skipped', 'expired']);
    }

    private function createDecisions(): void
    {
        Schema::create('approval_decisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('approval_request_step_id');
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('on_behalf_of_personnel_id')->nullable();
            $this->status($table, 'decision');
            $table->text('comment')->nullable();
            $this->asciiChar($table, 'approved_subject_hash', 64);
            $this->status($table, 'channel')->default('ui');
            $this->ts($table, 'decided_at');
            $this->auditCreated($table);

            $table->index(['approval_request_step_id', 'decided_at'], 'ix_approval_decisions_step');
            $table->foreign('approval_request_step_id', 'fk_approval_decisions_step')
                ->references('id')->on('approval_request_steps')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_approval_decisions_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('on_behalf_of_personnel_id', 'fk_approval_decisions_on_behalf')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('approval_decisions', 'decision', ['approved', 'rejected', 'returned', 'abstained']);
        $this->enumCheck('approval_decisions', 'channel', ['ui', 'api', 'external_service']);
    }

    private function createDelegationSnapshots(): void
    {
        Schema::create('delegation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('approval_decision_id');
            $table->unsignedBigInteger('source_delegation_id');
            $table->unsignedBigInteger('grantor_personnel_id');
            $table->unsignedBigInteger('delegate_personnel_id');
            $table->string('scope_snapshot', 120);
            $this->ts($table, 'valid_from_snapshot');
            $this->ts($table, 'valid_until_snapshot');
            $this->auditCreated($table);

            $table->unique('approval_decision_id', 'uk_delegation_snapshots_decision');
            $table->foreign('approval_decision_id', 'fk_delegation_snapshots_decision')
                ->references('id')->on('approval_decisions')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('source_delegation_id', 'fk_delegation_snapshots_delegation')
                ->references('id')->on('delegations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('grantor_personnel_id', 'fk_delegation_snapshots_grantor')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('delegate_personnel_id', 'fk_delegation_snapshots_delegate')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    /** B06'da ertelenen DMS kolonlari: revizyon ve inceleme kaydi onay talebine baglanir. */
    private function attachDmsColumns(): void
    {
        Schema::table('document_revisions', function (Blueprint $table): void {
            $table->unsignedBigInteger('approval_request_id')->nullable()->after('issued_at');
            $table->foreign('approval_request_id', 'fk_document_revisions_approval_request')
                ->references('id')->on('approval_requests')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('document_reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('approval_request_id')->nullable()->after('decided_at');
            $table->foreign('approval_request_id', 'fk_document_reviews_approval_request')
                ->references('id')->on('approval_requests')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('document_reviews', function (Blueprint $table): void {
            $table->dropForeign('fk_document_reviews_approval_request');
            $table->dropColumn('approval_request_id');
        });

        Schema::table('document_revisions', function (Blueprint $table): void {
            $table->dropForeign('fk_document_revisions_approval_request');
            $table->dropColumn('approval_request_id');
        });

        foreach (array_reverse(self::AUDITED_TABLES) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                foreach (['created_by_personnel_id', 'updated_by_personnel_id'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropForeign($this->fkName($table, $column));
                    }
                }
            });
        }

        Schema::table('approval_policies', function (Blueprint $table): void {
            $table->dropForeign('fk_approval_policies_current_version');
        });

        Schema::dropIfExists('delegation_snapshots');
        Schema::dropIfExists('approval_decisions');
        Schema::dropIfExists('approval_request_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_policy_versions');
        Schema::dropIfExists('approval_policies');
    }
};
