<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B13 (kismi) - Sertifika ve egitim kayitlari (docs/planning/06 SS6.3-6.6).
 *
 * Kullanici karariyla M02 kapsaminda erkene alindi; docs/planning/16 B13'u
 * M07'ye (Ik uzantilari) yerlestirir ve izin/mesai/ise alim tablolarini da
 * icerir - onlar ilgili modul gelene kadar bu batch'e eklenmez, ayri bir
 * migration dosyasiyla tamamlanir.
 *
 * document_revision_id / evidence_document_revision_id / certificate_document_revision_id
 * kolonlari DMS (B06/M04) gelene kadar eklenmez (B03/B04'teki bekleyen FK
 * deseniyle ayni yaklasim).
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const AUDITED_TABLES = [
        'certifications',
        'personnel_certifications',
        'trainings',
        'training_attendances',
    ];

    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->string('name');
            $table->string('issuer')->nullable();
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->boolean('is_field_mandatory')->default(false);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_certifications_code');
        });
        $this->enumCheck('certifications', 'status', ['active', 'inactive']);

        Schema::create('personnel_certifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('certification_id');
            $this->code($table, 'certificate_no', 64)->nullable();
            $table->date('issued_on');
            $table->date('valid_until')->nullable();
            $table->unsignedBigInteger('verified_by_personnel_id')->nullable();
            $this->status($table)->default('valid');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['personnel_id', 'certification_id', 'issued_on'], 'uk_personnel_certifications_issue');
            $table->index(['valid_until', 'status'], 'ix_personnel_certifications_expiry');
            $table->foreign('personnel_id', 'fk_personnel_certifications_personnel')
                ->references('id')->on('personnel')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('certification_id', 'fk_personnel_certifications_certification')
                ->references('id')->on('certifications')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('verified_by_personnel_id', 'fk_personnel_certifications_verifier')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('personnel_certifications', 'status', ['valid', 'expiring', 'expired', 'revoked']);

        Schema::create('trainings', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->string('name');
            $this->status($table, 'training_kind');
            $table->string('provider')->nullable();
            $table->date('planned_on')->nullable();
            $table->decimal('duration_hours', 6, 2)->nullable();
            $this->status($table)->default('planned');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_trainings_code');
        });
        $this->enumCheck('trainings', 'training_kind', ['internal', 'external', 'online', 'on_the_job']);
        $this->enumCheck('trainings', 'status', ['planned', 'completed', 'cancelled']);

        Schema::create('training_attendances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('training_id');
            $table->unsignedBigInteger('personnel_id');
            $table->date('attended_on')->nullable();
            $this->status($table, 'outcome')->default('registered');
            $table->decimal('score', 5, 2)->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['training_id', 'personnel_id'], 'uk_training_attendances_pair');
            $table->foreign('training_id', 'fk_training_attendances_training')
                ->references('id')->on('trainings')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_training_attendances_personnel')
                ->references('id')->on('personnel')->cascadeOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('training_attendances', 'outcome', ['registered', 'attended', 'passed', 'failed', 'absent']);

        foreach (self::AUDITED_TABLES as $table) {
            $this->personnelForeignKeys($table);
        }
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        foreach (array_reverse(self::AUDITED_TABLES) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                foreach (['created_by_personnel_id', 'updated_by_personnel_id', 'archived_by_personnel_id'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropForeign($this->fkName($table, $column));
                    }
                }
            });
        }

        Schema::table('training_attendances', function (Blueprint $table): void {
            $table->dropForeign('fk_training_attendances_personnel');
            $table->dropForeign('fk_training_attendances_training');
        });

        Schema::table('personnel_certifications', function (Blueprint $table): void {
            $table->dropForeign('fk_personnel_certifications_verifier');
            $table->dropForeign('fk_personnel_certifications_certification');
            $table->dropForeign('fk_personnel_certifications_personnel');
        });

        Schema::dropIfExists('training_attendances');
        Schema::dropIfExists('trainings');
        Schema::dropIfExists('personnel_certifications');
        Schema::dropIfExists('certifications');
    }
};
