<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B06 - DMS cekirdegi, tam tasarim (docs/planning/08 SS1.1-1.16, M04).
 *
 * Kullanici karariyla (D-66) 16 tablonun tamami kuruldu, yalniz henuz var
 * olmayan tablolara (approval_requests/M03 onay motoru, projects, parties,
 * functional_areas) giden FK'lar ertelendi (deferred FK deseni, B03/B04'te
 * de kullanildi): document_revisions.approval_request_id,
 * document_reviews.approval_request_id, documents.project_id/
 * functional_area_id, transmittals.project_id/recipient_party_id bu
 * batch'te yoktur; ilgili modul geldiginde ALTER ile eklenir.
 *
 * scan_status gercek bir virus tarayiciya bagli degildir (henuz karar
 * yok); yeni yuklenen dosya 'skipped' ile baslar.
 *
 * documents.current_revision_id ve document_revisions.document_id
 * karsilikli oldugu icin FK, her iki tablo da var olduktan sonra ayri
 * bir ALTER ile eklenir (docs/planning/16 B06 notu: "documents(id,
 * current_revision_id) composite").
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const AUDITED_TABLES = [
        'file_objects', 'document_types', 'documents', 'document_revisions',
        'document_revision_files', 'document_links', 'document_reviews',
        'document_distributions', 'document_acknowledgements', 'transmittals',
        'transmittal_items', 'document_templates', 'document_template_versions',
        'generated_outputs', 'legal_holds', 'legal_hold_documents',
    ];

    public function up(): void
    {
        Schema::create('file_objects', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'storage_disk', 32)->default('local');
            $this->ascii($table, 'storage_key', 512);
            $table->string('original_name');
            $this->code($table, 'extension', 32);
            $table->string('mime_type');
            $table->string('declared_mime_type')->nullable();
            $table->unsignedBigInteger('byte_size');
            $this->asciiChar($table, 'sha256', 64);
            $this->status($table, 'scan_status')->default('skipped');
            $this->ts($table, 'scanned_at')->nullable();
            $table->string('scanner_reference')->nullable();
            $table->string('quarantine_reason')->nullable();
            $table->boolean('is_derived')->default(false);
            $table->unsignedBigInteger('derived_from_file_object_id')->nullable();
            $this->status($table, 'derivation_kind')->nullable();
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->unsignedBigInteger('uploaded_by_personnel_id');
            $this->ts($table, 'uploaded_at');
            $table->unsignedBigInteger('retention_policy_id')->nullable();
            $this->status($table)->default('active');
            $this->ts($table, 'purged_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('storage_key', 'uk_file_objects_storage_key');
            $table->unique('sha256', 'uk_file_objects_sha256');
            $table->foreign('derived_from_file_object_id', 'fk_file_objects_derived_from')
                ->references('id')->on('file_objects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('uploaded_by_personnel_id', 'fk_file_objects_uploader')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('retention_policy_id', 'fk_file_objects_retention_policy')
                ->references('id')->on('retention_policies')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('file_objects', 'scan_status', ['pending', 'clean', 'infected', 'quarantined', 'failed', 'skipped']);
        $this->enumCheck('file_objects', 'derivation_kind', ['thumbnail', 'preview', 'pdf_render']);
        $this->enumCheck('file_objects', 'status', ['active', 'purged']);
        $this->check('file_objects', 'ck_file_objects_byte_size', '`byte_size` > 0');

        Schema::create('document_types', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->string('name');
            $this->status($table, 'discipline');
            $this->code($table, 'numbering_prefix', 32);
            $table->boolean('is_controlled')->default(true);
            $table->unsignedBigInteger('default_classification_id');
            $table->unsignedBigInteger('default_retention_policy_id');
            $table->json('allowed_extensions')->nullable();
            $table->unsignedBigInteger('max_byte_size')->nullable();
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_document_types_code');
            $table->foreign('default_classification_id', 'fk_document_types_classification')
                ->references('id')->on('security_classifications')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('default_retention_policy_id', 'fk_document_types_retention_policy')
                ->references('id')->on('retention_policies')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('document_types', 'discipline', [
            'general', 'commercial', 'engineering', 'electrical', 'automation', 'civil',
            'hse', 'quality', 'hr', 'finance', 'legal', 'social_media',
        ]);
        $this->enumCheck('document_types', 'status', ['active', 'inactive']);

        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'document_no', 32);
            $table->string('title');
            $table->unsignedBigInteger('document_type_id');
            $table->boolean('is_controlled')->default(true);
            $table->unsignedBigInteger('owner_personnel_id');
            $table->unsignedBigInteger('owner_org_unit_id')->nullable();
            $table->unsignedBigInteger('classification_id');
            $table->unsignedBigInteger('retention_policy_id');
            $this->ascii($table, 'default_language', 10)->default('tr');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $this->status($table)->default('draft');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('document_no', 'uk_documents_document_no');
            $table->foreign('document_type_id', 'fk_documents_type')
                ->references('id')->on('document_types')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_personnel_id', 'fk_documents_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_org_unit_id', 'fk_documents_owner_org_unit')
                ->references('id')->on('org_units')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('classification_id', 'fk_documents_classification')
                ->references('id')->on('security_classifications')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('retention_policy_id', 'fk_documents_retention_policy')
                ->references('id')->on('retention_policies')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('documents', 'status', ['draft', 'active', 'superseded', 'obsolete', 'on_hold', 'archived']);

        Schema::create('document_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('revision_no');
            $this->code($table, 'revision_code', 32);
            $this->ascii($table, 'language', 10);
            $table->string('title');
            $this->status($table, 'purpose');
            $this->status($table)->default('draft');
            $table->text('change_summary')->nullable();
            $this->asciiChar($table, 'content_hash', 64);
            $table->unsignedBigInteger('prepared_by_personnel_id');
            $this->ts($table, 'prepared_at');
            $table->unsignedBigInteger('checked_by_personnel_id')->nullable();
            $table->unsignedBigInteger('approved_by_personnel_id')->nullable();
            $this->ts($table, 'approved_at')->nullable();
            $this->ts($table, 'issued_at')->nullable();
            $table->unsignedBigInteger('superseded_by_revision_id')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['document_id', 'revision_no'], 'uk_document_revisions_no');
            $table->unique(['document_id', 'revision_code'], 'uk_document_revisions_code');
            $table->foreign('document_id', 'fk_document_revisions_document')
                ->references('id')->on('documents')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('prepared_by_personnel_id', 'fk_document_revisions_preparer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('checked_by_personnel_id', 'fk_document_revisions_checker')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approved_by_personnel_id', 'fk_document_revisions_approver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('superseded_by_revision_id', 'fk_document_revisions_superseded_by')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('document_revisions', 'purpose', [
            'for_information', 'for_review', 'for_approval', 'for_construction', 'as_built', 'final',
        ]);
        $this->enumCheck('document_revisions', 'status', [
            'draft', 'in_review', 'approved', 'issued', 'superseded', 'withdrawn',
        ]);

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreign('current_revision_id', 'fk_documents_current_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('document_revision_files', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('document_revision_id');
            $table->unsignedBigInteger('file_object_id');
            $this->status($table, 'file_role');
            $table->unsignedSmallInteger('sort_order')->default(0);

            if ($this->isMySql()) {
                $table->unsignedBigInteger('original_active_guard')
                    ->storedAs("CASE WHEN `file_role` = 'original' THEN `document_revision_id` END")
                    ->nullable();
            } else {
                $table->unsignedBigInteger('original_active_guard')->nullable();
            }

            $this->auditCreated($table);

            $table->unique(['document_revision_id', 'file_object_id', 'file_role'], 'uk_document_revision_files_role');
            $table->unique('original_active_guard', 'uk_document_revision_files_one_original');
            // restrictOnDelete: document_revision_id, original_active_guard'in taban
            // kolonu; MySQL uretilmis kolonun tabanina ON DELETE CASCADE'e izin vermez (1215).
            $table->foreign('document_revision_id', 'fk_document_revision_files_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('file_object_id', 'fk_document_revision_files_file')
                ->references('id')->on('file_objects')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('document_revision_files', 'file_role', [
            'original', 'native', 'pdf', 'preview', 'thumbnail', 'signature_page',
        ]);

        Schema::create('document_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_revision_id')->nullable();
            $this->code($table, 'target_type', 32);
            $table->unsignedBigInteger('target_id');
            $this->status($table, 'link_role');
            $table->unsignedBigInteger('linked_by_personnel_id');
            $this->auditCreated($table);

            $table->unique(['document_id', 'target_type', 'target_id', 'link_role'], 'uk_document_links_target');
            $table->foreign('document_id', 'fk_document_links_document')
                ->references('id')->on('documents')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_document_links_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('linked_by_personnel_id', 'fk_document_links_linker')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('document_links', 'link_role', ['attachment', 'evidence', 'reference', 'deliverable', 'source']);

        Schema::create('document_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('document_revision_id');
            $table->unsignedBigInteger('reviewer_personnel_id');
            $this->status($table, 'review_type');
            $this->status($table, 'decision');
            $table->text('comment')->nullable();
            $this->ts($table, 'decided_at');
            $this->auditCreated($table);

            $table->index(['document_revision_id', 'decided_at'], 'ix_document_reviews_revision');
            $table->foreign('document_revision_id', 'fk_document_reviews_revision')
                ->references('id')->on('document_revisions')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('reviewer_personnel_id', 'fk_document_reviews_reviewer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('document_reviews', 'review_type', ['check', 'approve', 'qa']);
        $this->enumCheck('document_reviews', 'decision', ['approved', 'approved_with_comments', 'rejected']);

        Schema::create('transmittals', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'transmittal_no', 32);
            $table->string('recipient_description');
            $this->status($table, 'purpose');
            $this->status($table)->default('draft');
            $table->unsignedBigInteger('issued_by_personnel_id')->nullable();
            $this->ts($table, 'issued_at')->nullable();
            $table->unsignedBigInteger('cover_document_revision_id')->nullable();
            $table->string('external_reference')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('transmittal_no', 'uk_transmittals_no');
            $table->foreign('issued_by_personnel_id', 'fk_transmittals_issuer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('cover_document_revision_id', 'fk_transmittals_cover_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('transmittals', 'purpose', ['for_information', 'for_review', 'for_approval', 'for_construction', 'final']);
        $this->enumCheck('transmittals', 'status', ['draft', 'issued', 'acknowledged', 'cancelled']);

        Schema::create('document_distributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('document_revision_id');
            $table->unsignedBigInteger('recipient_personnel_id');
            $this->status($table, 'distribution_kind');
            $table->boolean('requires_acknowledgement')->default(false);
            $table->unsignedBigInteger('distributed_by_personnel_id');
            $this->ts($table, 'distributed_at');
            $table->unsignedBigInteger('transmittal_id')->nullable();
            $this->auditCreated($table);

            $table->unique(['document_revision_id', 'recipient_personnel_id', 'distribution_kind'], 'uk_document_distributions_pair');
            $table->foreign('document_revision_id', 'fk_document_distributions_revision')
                ->references('id')->on('document_revisions')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('recipient_personnel_id', 'fk_document_distributions_recipient')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('distributed_by_personnel_id', 'fk_document_distributions_sender')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('transmittal_id', 'fk_document_distributions_transmittal')
                ->references('id')->on('transmittals')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('document_distributions', 'distribution_kind', ['for_action', 'for_information', 'controlled_copy']);

        Schema::create('document_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('document_revision_id');
            $table->unsignedBigInteger('personnel_id');
            $this->status($table, 'acknowledgement_kind');
            $this->ts($table, 'acknowledged_at');
            $table->text('comment')->nullable();
            $this->auditCreated($table);

            $table->unique(['document_revision_id', 'personnel_id', 'acknowledgement_kind'], 'uk_document_acknowledgements_pair');
            $table->foreign('document_revision_id', 'fk_document_acknowledgements_revision')
                ->references('id')->on('document_revisions')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_document_acknowledgements_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('document_acknowledgements', 'acknowledgement_kind', ['read', 'accepted', 'trained']);

        Schema::create('transmittal_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('transmittal_id');
            $table->unsignedBigInteger('document_revision_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('copies')->default(1);
            $this->auditCreated($table);

            $table->unique(['transmittal_id', 'document_revision_id'], 'uk_transmittal_items_pair');
            $table->foreign('transmittal_id', 'fk_transmittal_items_transmittal')
                ->references('id')->on('transmittals')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_transmittal_items_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->string('name');
            $this->status($table, 'output_kind');
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_document_templates_code');
        });
        $this->enumCheck('document_templates', 'output_kind', ['report_pdf', 'document_pdf', 'letter_pdf', 'xlsx_export']);
        $this->enumCheck('document_templates', 'status', ['active', 'retired']);

        Schema::create('document_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('document_template_id');
            $table->unsignedInteger('version_no');
            $this->ascii($table, 'locale', 10);
            $this->code($table, 'view_key', 128);
            $table->json('layout_config')->nullable();
            $table->json('required_field_keys')->nullable();
            $this->status($table)->default('draft');
            $this->asciiChar($table, 'content_hash', 64);
            $table->unsignedBigInteger('published_by_personnel_id')->nullable();
            $this->ts($table, 'published_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['document_template_id', 'locale', 'version_no'], 'uk_document_template_versions_no');
            $table->foreign('document_template_id', 'fk_document_template_versions_template')
                ->references('id')->on('document_templates')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('published_by_personnel_id', 'fk_document_template_versions_publisher')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('document_template_versions', 'status', ['draft', 'published', 'superseded']);

        Schema::create('generated_outputs', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('template_version_id');
            $this->ascii($table, 'locale', 10);
            $this->status($table, 'output_format');
            $table->unsignedInteger('output_no');
            $this->ascii($table, 'idempotency_key', 64);
            $table->unsignedBigInteger('personnel_id');
            $this->ts($table, 'requested_at');
            $this->status($table)->default('queued');
            $table->json('missing_fields_snapshot')->nullable();
            $table->unsignedBigInteger('file_object_id')->nullable();
            $this->asciiChar($table, 'output_hash', 64)->nullable();
            $this->ts($table, 'completed_at')->nullable();
            $this->code($table, 'safe_error_code', 32)->nullable();
            $this->auditCreated($table);

            $table->unique(['source_type', 'source_id', 'locale', 'output_no'], 'uk_generated_outputs_no');
            $table->unique('idempotency_key', 'uk_generated_outputs_idempotency');
            $table->foreign('template_version_id', 'fk_generated_outputs_template_version')
                ->references('id')->on('document_template_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_generated_outputs_requester')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('file_object_id', 'fk_generated_outputs_file')
                ->references('id')->on('file_objects')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('generated_outputs', 'output_format', ['pdf', 'xlsx', 'docx']);
        $this->enumCheck('generated_outputs', 'status', [
            'queued', 'rendering', 'completed', 'failed', 'blocked_missing_fields', 'superseded',
        ]);

        Schema::create('legal_holds', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->string('name');
            $table->text('reason');
            $table->unsignedBigInteger('personnel_id');
            $table->unsignedBigInteger('approved_by_personnel_id')->nullable();
            $this->status($table)->default('draft');
            $this->ts($table, 'starts_at');
            $this->ts($table, 'released_at')->nullable();
            $table->text('release_reason')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_legal_holds_code');
            $table->foreign('personnel_id', 'fk_legal_holds_requester')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approved_by_personnel_id', 'fk_legal_holds_approver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('legal_holds', 'status', ['draft', 'active', 'released']);

        Schema::create('legal_hold_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legal_hold_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_revision_id')->nullable();
            $table->unsignedBigInteger('added_by_personnel_id');
            $this->ts($table, 'added_at');
            $this->auditCreated($table);

            $table->unique(['legal_hold_id', 'document_id', 'document_revision_id'], 'uk_legal_hold_documents_scope');
            $table->foreign('legal_hold_id', 'fk_legal_hold_documents_hold')
                ->references('id')->on('legal_holds')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('document_id', 'fk_legal_hold_documents_document')
                ->references('id')->on('documents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_legal_hold_documents_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('added_by_personnel_id', 'fk_legal_hold_documents_adder')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });

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

        Schema::dropIfExists('legal_hold_documents');
        Schema::dropIfExists('legal_holds');
        Schema::dropIfExists('generated_outputs');
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('transmittal_items');
        Schema::dropIfExists('document_acknowledgements');
        Schema::dropIfExists('document_distributions');
        Schema::dropIfExists('transmittals');
        Schema::dropIfExists('document_reviews');
        Schema::dropIfExists('document_links');
        Schema::dropIfExists('document_revision_files');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign('fk_documents_current_revision');
        });
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('file_objects');
    }
};
