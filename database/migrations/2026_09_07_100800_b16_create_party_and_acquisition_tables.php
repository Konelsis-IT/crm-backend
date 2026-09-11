<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B16 - Party, business case, ihale, teklif, sozlesme ve Operasyona devir
 * (docs/planning/10 SS1-5, M08-M11; karar D-67).
 *
 * Kullanici karariyla ("tam zinciri kur") 10 nolu sozlugun 44 tablosundan
 * `business_number_allocations` disindaki 42'si burada kurulur (o tablo B01'de
 * zaten var; AllocateBusinessNumber servisi ayni siradan numara ayirir).
 *
 * Acik kararlarin onerilen degerleri uygulanmistir: D-28 (party subtype
 * exact-one, rol basina tek aktif satir guard'i), D-29 (business case
 * basina 1:N teklif + `selected_guard`), D-10 (`ohv.contract_version_id`
 * semada NULL olabilir; zorunlulugu servis kurali uygular), D-11 (tek
 * global sira, business case basina tek proje).
 *
 * Henuz var olmayan tablolara giden FK'lar ertelenmis FK deseniyle (B06
 * ile ayni) simdilik yoktur: `bd_activities.follow_up_task_id` (B11 tasks),
 * `proposal_versions/contract_versions/handoff_reviews.approval_request_id`
 * (B07 onay motoru), `boq_items.catalog_item_id` (B18 katalog). Ilgili
 * batch geldiginde ALTER ile eklenir.
 *
 * Kok tablolardaki `current_version_id`/`accepted_version_id` ile surum
 * tablosunun kok FK'si karsilikli oldugu icin composite FK'lar her iki
 * tablo da olustuktan sonra ayri ALTER ile kurulur (13 SS2).
 *
 * MySQL 8 CHECK ifadesinde AUTO_INCREMENT `id` kolonuna atif yapamaz; bu
 * yuzden `estimate_lines.parent_line_id <> id` kurali servis katmanindadir
 * (06 SS1.5 N-10). STORED generated guard kolonlarinin taban kolonu olan
 * FK'lar yalniz RESTRICT ile baglanir (MySQL 1215).
 *
 * DMS'te ertelenmis olan `transmittals.recipient_party_id` bu batch'in
 * sonunda eklenir.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const AUDITED_TABLES = [
        'parties', 'party_roles', 'organization_profiles', 'person_profiles', 'addresses',
        'communication_points', 'contact_relationships', 'party_licenses', 'party_certificates',
        'party_annual_reviews', 'business_cases', 'business_codes', 'opportunities',
        'opportunity_stage_histories', 'business_development_activities',
        'business_development_activity_participants', 'tender_sources', 'tender_notices',
        'tender_notice_versions', 'tender_requirements', 'tender_deadlines', 'proposals',
        'proposal_versions', 'proposal_documents', 'compliance_items', 'deviations', 'brand_items',
        'responsibility_matrix_items', 'estimate_versions', 'estimate_lines', 'pricing_scenarios',
        'boq_items', 'contracts', 'contract_versions', 'contract_parties', 'contract_documents',
        'contract_obligations', 'contract_milestones', 'operation_handoffs',
        'operation_handoff_versions', 'handoff_items', 'handoff_reviews',
    ];

    public function up(): void
    {
        $this->createPartyTables();
        $this->createBusinessCaseTables();
        $this->createTenderTables();
        $this->createProposalTables();
        $this->createContractTables();
        $this->createHandoffTables();
        $this->addCrossReferences();

        foreach (self::AUDITED_TABLES as $table) {
            $this->personnelForeignKeys($table);
        }
    }

    private function createPartyTables(): void
    {
        Schema::create('parties', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'party_no', 32);
            $this->status($table, 'party_kind');
            $table->string('display_name');
            $table->string('normalized_name');
            $this->asciiChar($table, 'country_code', 2)->nullable();
            $this->ascii($table, 'default_locale', 10)->default('tr');
            $this->ascii($table, 'duplicate_check_hash', 64)->nullable();
            $this->status($table)->default('prospect');
            $table->unsignedBigInteger('merged_into_party_id')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);
            $this->auditArchived($table);

            $table->unique('party_no', 'uk_parties_party_no');
            $table->index('normalized_name', 'ix_parties_normalized_name');
            $table->index('duplicate_check_hash', 'ix_parties_duplicate_check_hash');
            $table->foreign('country_code', 'fk_parties_country')
                ->references('code')->on('countries')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('merged_into_party_id', 'fk_parties_merged_into')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('parties', 'party_kind', ['organization', 'person']);
        $this->enumCheck('parties', 'status', ['prospect', 'active', 'inactive', 'blocked', 'merged']);
        $this->check('parties', 'ck_parties_merged_target', "`status` <> 'merged' OR `merged_into_party_id` IS NOT NULL");

        Schema::create('party_roles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('party_id');
            $this->status($table, 'role_code');
            $this->status($table)->default('active');
            $table->unsignedBigInteger('approved_by_personnel_id')->nullable();
            $this->ts($table, 'valid_from');
            $this->ts($table, 'valid_until')->nullable();

            if ($this->isMySql()) {
                $table->unsignedTinyInteger('active_guard')
                    ->storedAs('CASE WHEN `valid_until` IS NULL THEN 1 END')
                    ->nullable();
            } else {
                $table->unsignedTinyInteger('active_guard')->nullable();
            }

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['party_id', 'role_code', 'active_guard'], 'uk_party_roles_active_guard');
            $table->index(['party_id', 'valid_from', 'valid_until'], 'ix_party_roles_party_range');
            $table->foreign('party_id', 'fk_party_roles_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approved_by_personnel_id', 'fk_party_roles_approver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('party_roles', 'role_code', [
            'customer', 'supplier', 'subcontractor', 'partner', 'employer', 'investor', 'consultant', 'carrier', 'authority',
        ]);
        $this->enumCheck('party_roles', 'status', ['active', 'suspended', 'ended']);
        $this->validRangeCheck('party_roles');

        Schema::create('organization_profiles', function (Blueprint $table): void {
            $table->unsignedBigInteger('party_id')->primary();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $this->code($table, 'registration_no')->nullable();
            $table->string('tax_office', 100)->nullable();
            $this->code($table, 'tax_number', 32)->nullable();
            $table->smallInteger('founded_year')->nullable();
            $table->string('website_url', 2048)->nullable();
            $this->code($table, 'sector_code', 32)->nullable();
            $this->code($table, 'personnel_band', 32)->nullable();
            $table->unsignedBigInteger('group_parent_party_id')->nullable();
            $table->boolean('is_public_company')->default(false);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('tax_number', 'uk_organization_profiles_tax_number');
            $table->foreign('party_id', 'fk_organization_profiles_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('group_parent_party_id', 'fk_organization_profiles_group_parent')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('person_profiles', function (Blueprint $table): void {
            $table->unsignedBigInteger('party_id')->primary();
            $table->string('given_name');
            $table->string('family_name');
            $table->string('title', 100)->nullable();
            $table->string('job_title')->nullable();
            $this->ascii($table, 'preferred_locale', 10)->default('tr');
            $this->status($table, 'consent_status')->default('pending');
            $this->ts($table, 'consent_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->foreign('party_id', 'fk_person_profiles_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('person_profiles', 'consent_status', ['pending', 'granted', 'withdrawn']);

        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('party_id');
            $this->status($table, 'address_type');
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100);
            $this->code($table, 'postal_code', 32)->nullable();
            $this->asciiChar($table, 'country_code', 2);
            $table->boolean('is_primary')->default(false);

            if ($this->isMySql()) {
                $table->unsignedTinyInteger('primary_guard')
                    ->storedAs('CASE WHEN `is_primary` = 1 THEN 1 END')
                    ->nullable();
            } else {
                $table->unsignedTinyInteger('primary_guard')->nullable();
            }

            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['party_id', 'address_type', 'primary_guard'], 'uk_addresses_primary_guard');
            $table->foreign('party_id', 'fk_addresses_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('country_code', 'fk_addresses_country')
                ->references('code')->on('countries')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('addresses', 'address_type', ['registered', 'billing', 'shipping', 'site', 'office', 'other']);
        $this->enumCheck('addresses', 'status', ['active', 'inactive']);

        Schema::create('communication_points', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('party_id');
            $this->status($table, 'channel_type');
            $table->string('value', 100);
            $table->string('normalized_value', 100);
            $this->code($table, 'purpose', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['party_id', 'channel_type', 'normalized_value'], 'uk_communication_points_party_channel_value');
            $table->foreign('party_id', 'fk_communication_points_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('communication_points', 'channel_type', ['email', 'phone', 'mobile', 'fax', 'website', 'linkedin', 'other']);
        $this->enumCheck('communication_points', 'status', ['active', 'inactive', 'bounced']);

        Schema::create('contact_relationships', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_party_id');
            $table->unsignedBigInteger('contact_party_id');
            $this->status($table, 'relationship_role');
            $table->string('department_note', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $this->ts($table, 'valid_from');
            $this->ts($table, 'valid_until')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['organization_party_id', 'valid_from', 'valid_until'], 'ix_contact_relationships_org_range');
            $table->foreign('organization_party_id', 'fk_contact_relationships_organization')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('contact_party_id', 'fk_contact_relationships_contact')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('contact_relationships', 'relationship_role', [
            'decision_maker', 'technical_contact', 'procurement_contact', 'finance_contact', 'executive', 'other',
        ]);
        $this->validRangeCheck('contact_relationships');
        $this->check('contact_relationships', 'ck_contact_relationships_no_self_link', '`organization_party_id` <> `contact_party_id`');

        foreach (['party_licenses' => 'license', 'party_certificates' => 'certificate'] as $tableName => $prefix) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName, $prefix): void {
                $table->id();
                $table->unsignedBigInteger('party_id');
                $this->code($table, "{$prefix}_type", 32);
                $this->code($table, "{$prefix}_no");
                $table->string('issuer')->nullable();
                $table->date('issued_on')->nullable();
                $table->date('valid_until')->nullable();
                $table->unsignedBigInteger('document_revision_id')->nullable();
                $this->status($table)->default('valid');
                $this->auditCreated($table);
                $this->auditUpdated($table);

                $table->unique(['party_id', "{$prefix}_type", "{$prefix}_no"], "uk_{$tableName}_party_type_no");
                $table->index('valid_until', "ix_{$tableName}_valid_until");
                $table->foreign('party_id', "fk_{$tableName}_party")
                    ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
                $table->foreign('document_revision_id', "fk_{$tableName}_document_revision")
                    ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            });
            $this->enumCheck($tableName, 'status', ['valid', 'expiring', 'expired', 'revoked']);
        }

        Schema::create('party_annual_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('party_id');
            $table->smallInteger('review_year');
            $table->unsignedBigInteger('reviewer_employee_id');
            $this->status($table, 'outcome')->default('pending');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('summary')->nullable();
            $this->ts($table, 'reviewed_at')->nullable();
            $table->date('next_review_on')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['party_id', 'review_year'], 'uk_party_annual_reviews_party_year');
            $table->foreign('party_id', 'fk_party_annual_reviews_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('reviewer_employee_id', 'fk_party_annual_reviews_reviewer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('party_annual_reviews', 'outcome', ['pending', 'approved', 'conditional', 'rejected']);
    }

    private function createBusinessCaseTables(): void
    {
        Schema::create('business_cases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sequence_no');
            $table->unsignedBigInteger('legal_entity_id');
            $table->unsignedBigInteger('primary_party_id');
            $table->string('title');
            $table->text('short_description')->nullable();
            $this->asciiChar($table, 'country_code', 2);
            $this->asciiChar($table, 'currency_code', 3);
            $this->code($table, 'project_type_code', 32)->nullable();
            $this->status($table, 'source_kind');
            $this->status($table, 'criticality')->default('normal');
            $this->status($table, 'lifecycle_segment')->default('acquisition');
            $this->status($table, 'acquisition_stage')->default('business_development');
            $this->status($table, 'outcome')->default('open');
            $this->code($table, 'outcome_reason_code', 32)->nullable();
            $this->ts($table, 'outcome_at')->nullable();
            $table->unsignedBigInteger('owner_employee_id');
            $table->unsignedBigInteger('proposal_owner_employee_id')->nullable();
            $table->decimal('estimated_value', 20, 4)->nullable();
            $table->unsignedBigInteger('classification_id');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('sequence_no', 'uk_business_cases_sequence_no');
            $table->unique(['id', 'sequence_no'], 'uk_business_cases_id_sequence_no');
            $table->index(['acquisition_stage', 'outcome'], 'ix_business_cases_stage_outcome');
            $table->foreign('sequence_no', 'fk_business_cases_sequence')
                ->references('sequence_no')->on('business_number_allocations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('legal_entity_id', 'fk_business_cases_legal_entity')
                ->references('id')->on('legal_entities')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('primary_party_id', 'fk_business_cases_primary_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('country_code', 'fk_business_cases_country')
                ->references('code')->on('countries')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_business_cases_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_employee_id', 'fk_business_cases_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('proposal_owner_employee_id', 'fk_business_cases_proposal_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('classification_id', 'fk_business_cases_classification')
                ->references('id')->on('security_classifications')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('business_cases', 'source_kind', ['email', 'manual', 'tender_source', 'referral', 'existing_customer']);
        $this->enumCheck('business_cases', 'criticality', ['normal', 'critical']);
        $this->enumCheck('business_cases', 'lifecycle_segment', ['acquisition', 'operation']);
        $this->enumCheck('business_cases', 'acquisition_stage', [
            'business_development', 'offer_preparation', 'offer_review', 'submitted', 'negotiation', 'won',
            'handover_preparing', 'handover_review', 'handover_accepted', 'lost', 'cancelled',
        ]);
        $this->enumCheck('business_cases', 'outcome', ['open', 'won', 'lost', 'cancelled']);

        Schema::create('business_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id');
            $table->unsignedBigInteger('sequence_no');
            $this->status($table, 'code_kind');

            if ($this->isMySql()) {
                $table->string('formatted_code', 24)
                    ->storedAs("CONCAT(CASE `code_kind` WHEN 'offer' THEN 'TKLF-' WHEN 'project' THEN 'PRJ-' END, `sequence_no`)");
            } else {
                $table->string('formatted_code', 24)->nullable();
            }

            $this->ts($table, 'issued_at');
            $table->unsignedBigInteger('issued_by_personnel_id');
            $table->unsignedBigInteger('predecessor_code_id')->nullable();
            $this->status($table)->default('active');
            $this->auditCreated($table);

            $table->unique(['business_case_id', 'code_kind'], 'uk_business_codes_case_kind');
            $table->unique('formatted_code', 'uk_business_codes_formatted_code');
            // restrictOnDelete: sequence_no, formatted_code generated kolonunun tabani (MySQL 1215).
            $table->foreign(['business_case_id', 'sequence_no'], 'fk_business_codes_case_agg')
                ->references(['id', 'sequence_no'])->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('issued_by_personnel_id', 'fk_business_codes_issuer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('predecessor_code_id', 'fk_business_codes_predecessor')
                ->references('id')->on('business_codes')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('business_codes', 'code_kind', ['offer', 'project']);
        $this->enumCheck('business_codes', 'status', ['active', 'historical']);

        Schema::create('opportunities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id');
            $this->status($table, 'stage')->default('identified');
            $table->decimal('probability_pct', 7, 4)->default(0);
            $table->decimal('expected_value', 20, 4)->nullable();
            $table->date('expected_decision_on')->nullable();
            $this->code($table, 'market_code', 32)->nullable();
            $this->status($table, 'bid_decision')->default('pending');
            $table->unsignedBigInteger('bid_decision_by_personnel_id')->nullable();
            $this->ts($table, 'bid_decision_at')->nullable();
            $table->text('bid_decision_reason')->nullable();
            $table->text('competitor_note')->nullable();
            $this->ts($table, 'handoff_checklist_completed_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('business_case_id', 'uk_opportunities_business_case');
            $table->foreign('business_case_id', 'fk_opportunities_business_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('bid_decision_by_personnel_id', 'fk_opportunities_bid_decider')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('opportunities', 'stage', [
            'identified', 'qualified', 'bid_decision_pending', 'bid', 'no_bid', 'converted_to_proposal', 'dropped',
        ]);
        $this->enumCheck('opportunities', 'bid_decision', ['pending', 'bid', 'no_bid']);
        $this->check('opportunities', 'ck_opportunities_pct_range', '`probability_pct` >= 0 AND `probability_pct` <= 100');

        Schema::create('opportunity_stage_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('opportunity_id');
            $this->status($table, 'from_stage')->nullable();
            $this->status($table, 'to_stage');
            $table->unsignedBigInteger('changed_by_personnel_id');
            $this->ts($table, 'changed_at');
            $table->text('reason')->nullable();
            $this->auditCreated($table);

            $table->index(['opportunity_id', 'changed_at'], 'ix_opportunity_stage_histories_opportunity');
            $table->foreign('opportunity_id', 'fk_opportunity_stage_histories_opportunity')
                ->references('id')->on('opportunities')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('changed_by_personnel_id', 'fk_opportunity_stage_histories_changer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('business_development_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id')->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $this->status($table, 'activity_type');
            $table->string('subject');
            $this->ts($table, 'occurred_at');
            $this->ascii($table, 'timezone', 64)->default('Europe/Istanbul');
            $table->string('location', 100)->nullable();
            $table->unsignedBigInteger('organizer_employee_id');
            $table->text('outcome_summary')->nullable();
            $table->text('next_action')->nullable();
            $this->ts($table, 'next_action_due_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['business_case_id', 'occurred_at'], 'ix_bd_activities_case_occurred');
            $table->foreign('business_case_id', 'fk_bd_activities_business_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('party_id', 'fk_bd_activities_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('organizer_employee_id', 'fk_bd_activities_organizer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('business_development_activities', 'activity_type', [
            'meeting', 'visit', 'call', 'email', 'event', 'site_survey', 'other',
        ]);
        $this->check('business_development_activities', 'ck_bd_activities_any_context', '`business_case_id` IS NOT NULL OR `party_id` IS NOT NULL');

        Schema::create('business_development_activity_participants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('activity_id');
            $table->unsignedBigInteger('personnel_id')->nullable();
            $table->unsignedBigInteger('contact_party_id')->nullable();
            $this->status($table, 'participation_role')->default('attendee');
            $this->auditCreated($table);

            $table->unique(['activity_id', 'personnel_id', 'contact_party_id'], 'uk_bdap_activity_targets');
            $table->foreign('activity_id', 'fk_bdap_activity')
                ->references('id')->on('business_development_activities')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_bdap_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('contact_party_id', 'fk_bdap_contact_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('business_development_activity_participants', 'participation_role', ['host', 'attendee', 'presenter']);
        $this->check('business_development_activity_participants', 'ck_bdap_exact_one', '(`personnel_id` IS NOT NULL) + (`contact_party_id` IS NOT NULL) = 1');
    }

    private function createTenderTables(): void
    {
        Schema::create('tender_sources', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code');
            $table->string('name_tr');
            $table->string('name_en');
            $this->status($table, 'source_type');
            $table->string('base_url', 2048)->nullable();
            $this->status($table, 'access_mode')->default('manual');
            $table->boolean('scraping_allowed')->default(false);
            $table->string('terms_reference', 100)->nullable();
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_tender_sources_code');
        });
        $this->enumCheck('tender_sources', 'source_type', [
            'public_procurement', 'international_finance', 'provincial_bank', 'private_invitation', 'marketplace', 'other',
        ]);
        $this->enumCheck('tender_sources', 'access_mode', ['manual', 'email', 'api']);
        $this->enumCheck('tender_sources', 'status', ['active', 'inactive']);

        Schema::create('tender_notices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id');
            $table->unsignedBigInteger('tender_source_id');
            $table->string('external_notice_id', 100)->nullable();
            $table->string('title');
            $table->unsignedBigInteger('issuer_party_id')->nullable();
            $table->string('notice_url', 2048)->nullable();
            $this->ts($table, 'captured_at');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $this->status($table)->default('captured');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['tender_source_id', 'external_notice_id'], 'uk_tender_notices_source_external_id');
            $table->foreign('business_case_id', 'fk_tender_notices_business_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('tender_source_id', 'fk_tender_notices_source')
                ->references('id')->on('tender_sources')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('issuer_party_id', 'fk_tender_notices_issuer_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('tender_notices', 'status', [
            'captured', 'screening', 'pursuing', 'not_pursued', 'submitted', 'awarded', 'lost', 'cancelled',
        ]);

        Schema::create('tender_notice_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tender_notice_id');
            $table->unsignedInteger('version_no');
            $table->date('published_on')->nullable();
            $this->ascii($table, 'source_hash', 64);
            $table->unsignedBigInteger('source_document_revision_id')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('captured_by_personnel_id');
            $this->status($table)->default('current');
            $this->auditCreated($table);

            $table->unique(['tender_notice_id', 'version_no'], 'uk_tender_notice_versions_notice_version_no');
            $table->unique(['tender_notice_id', 'id'], 'uk_tender_notice_versions_notice_id');
            $table->foreign('tender_notice_id', 'fk_tender_notice_versions_notice')
                ->references('id')->on('tender_notices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('source_document_revision_id', 'fk_tender_notice_versions_source_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('captured_by_personnel_id', 'fk_tender_notice_versions_capturer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('tender_notice_versions', 'status', ['current', 'superseded']);

        Schema::create('tender_requirements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tender_notice_version_id');
            $this->code($table, 'requirement_code', 32);
            $this->status($table, 'requirement_type');
            $table->text('description');
            $table->boolean('is_mandatory')->default(true);
            $this->status($table, 'compliance_state')->default('unknown');
            $table->unsignedBigInteger('evaluated_by_personnel_id')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['tender_notice_version_id', 'requirement_code'], 'uk_tender_requirements_version_code');
            $table->foreign('tender_notice_version_id', 'fk_tender_requirements_version')
                ->references('id')->on('tender_notice_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('evaluated_by_personnel_id', 'fk_tender_requirements_evaluator')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('tender_requirements', 'requirement_type', ['eligibility', 'technical', 'financial', 'document', 'legal', 'experience']);
        $this->enumCheck('tender_requirements', 'compliance_state', ['unknown', 'met', 'partially_met', 'not_met', 'waived']);

        Schema::create('tender_deadlines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tender_notice_version_id');
            $this->status($table, 'deadline_type');
            $table->date('local_due_date');
            $table->time('local_due_time', 0)->nullable();
            $this->ascii($table, 'timezone', 64)->default('Europe/Istanbul');
            $this->ts($table, 'due_at_utc');
            $this->ts($table, 'alert_generated_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['tender_notice_version_id', 'deadline_type'], 'uk_tender_deadlines_version_type');
            $table->index('due_at_utc', 'ix_tender_deadlines_due_at');
            $table->foreign('tender_notice_version_id', 'fk_tender_deadlines_version')
                ->references('id')->on('tender_notice_versions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('tender_deadlines', 'deadline_type', ['clarification', 'site_visit', 'submission', 'opening', 'bond_validity', 'award']);
    }

    private function createProposalTables(): void
    {
        Schema::create('proposals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id');
            $this->code($table, 'proposal_no');
            $table->string('title');
            $table->unsignedBigInteger('owner_employee_id');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $this->status($table)->default('draft');
            $table->boolean('is_selected')->default(false);

            if ($this->isMySql()) {
                $table->unsignedBigInteger('selected_guard')
                    ->storedAs('CASE WHEN `is_selected` = 1 THEN `business_case_id` END')
                    ->nullable();
            } else {
                $table->unsignedBigInteger('selected_guard')->nullable();
            }

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('proposal_no', 'uk_proposals_proposal_no');
            $table->unique('selected_guard', 'uk_proposals_selected_guard');
            // restrictOnDelete: business_case_id, selected_guard'in taban kolonu (MySQL 1215).
            $table->foreign('business_case_id', 'fk_proposals_business_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('owner_employee_id', 'fk_proposals_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('proposals', 'status', [
            'draft', 'in_review', 'approved', 'submitted', 'negotiation', 'accepted', 'rejected', 'withdrawn', 'superseded',
        ]);

        Schema::create('proposal_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_id');
            $table->unsignedInteger('version_no');
            $this->ascii($table, 'locale', 10)->default('tr');
            $this->status($table)->default('draft');
            $this->ascii($table, 'version_hash', 64)->nullable();
            $this->asciiChar($table, 'currency_code', 3);
            $table->decimal('total_price', 20, 4)->nullable();
            $table->decimal('margin_pct', 7, 4)->nullable();
            $table->date('validity_until')->nullable();
            $table->boolean('is_critical_route')->default(false);
            $table->unsignedBigInteger('project_group_opinion_document_revision_id')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('prepared_by_personnel_id');
            $table->unsignedBigInteger('approved_by_personnel_id')->nullable();
            $this->ts($table, 'approved_at')->nullable();
            $this->ts($table, 'submitted_at')->nullable();
            $this->status($table, 'submitted_channel')->nullable();
            $table->unsignedBigInteger('submission_evidence_document_revision_id')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['proposal_id', 'version_no'], 'uk_proposal_versions_proposal_version_no');
            $table->unique(['proposal_id', 'id'], 'uk_proposal_versions_proposal_id');
            $table->foreign('proposal_id', 'fk_proposal_versions_proposal')
                ->references('id')->on('proposals')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_proposal_versions_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('project_group_opinion_document_revision_id', 'fk_proposal_versions_pg_opinion_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('prepared_by_personnel_id', 'fk_proposal_versions_preparer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approved_by_personnel_id', 'fk_proposal_versions_approver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('submission_evidence_document_revision_id', 'fk_proposal_versions_submission_evidence_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('proposal_versions', 'status', ['draft', 'review', 'approved', 'submitted', 'superseded', 'withdrawn']);
        $this->enumCheck('proposal_versions', 'submitted_channel', ['email', 'portal', 'hand_delivered', 'courier']);
        $this->check('proposal_versions', 'ck_proposal_versions_pct_range', '`margin_pct` IS NULL OR (`margin_pct` >= 0 AND `margin_pct` <= 100)');

        Schema::create('proposal_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_version_id');
            $table->unsignedBigInteger('document_revision_id');
            $this->status($table, 'document_role');
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);

            $table->unique(['proposal_version_id', 'document_revision_id', 'document_role'], 'uk_proposal_documents_version_revision_role');
            $table->foreign('proposal_version_id', 'fk_proposal_documents_version')
                ->references('id')->on('proposal_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_proposal_documents_document_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('proposal_documents', 'document_role', [
            'technical_offer', 'commercial_offer', 'spec_compliance', 'brand_list', 'responsibility_matrix',
            'schedule', 'site_survey', 'supplier_quote', 'kmz', 'photo', 'other',
        ]);

        Schema::create('compliance_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_version_id');
            $table->unsignedBigInteger('tender_requirement_id')->nullable();
            $this->code($table, 'requirement_code', 32);
            $table->text('description');
            $this->status($table, 'compliance_state')->default('comply');
            $table->text('note')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['proposal_version_id', 'requirement_code'], 'uk_compliance_items_version_code');
            $table->foreign('proposal_version_id', 'fk_compliance_items_version')
                ->references('id')->on('proposal_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('tender_requirement_id', 'fk_compliance_items_tender_requirement')
                ->references('id')->on('tender_requirements')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('compliance_items', 'compliance_state', ['comply', 'partial', 'deviate', 'not_applicable']);

        Schema::create('deviations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_version_id');
            $table->unsignedBigInteger('compliance_item_id')->nullable();
            $this->status($table, 'deviation_type');
            $table->text('description');
            $table->text('justification')->nullable();
            $this->status($table)->default('proposed');
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->foreign('proposal_version_id', 'fk_deviations_version')
                ->references('id')->on('proposal_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('compliance_item_id', 'fk_deviations_compliance_item')
                ->references('id')->on('compliance_items')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('deviations', 'deviation_type', ['technical', 'commercial', 'schedule', 'legal']);
        $this->enumCheck('deviations', 'status', ['proposed', 'accepted_by_customer', 'rejected_by_customer', 'withdrawn']);

        Schema::create('brand_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_version_id');
            $this->code($table, 'item_code', 32);
            $table->string('item_description');
            $table->string('proposed_brand');
            $table->string('alternative_brand')->nullable();
            $this->asciiChar($table, 'origin_country_code', 2)->nullable();
            $this->status($table, 'approval_state')->default('proposed');
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['proposal_version_id', 'item_code'], 'uk_brand_items_version_code');
            $table->foreign('proposal_version_id', 'fk_brand_items_version')
                ->references('id')->on('proposal_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('origin_country_code', 'fk_brand_items_origin_country')
                ->references('code')->on('countries')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('brand_items', 'approval_state', ['proposed', 'pending', 'customer_approved', 'customer_rejected']);

        Schema::create('responsibility_matrix_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_version_id');
            $this->code($table, 'scope_code', 32);
            $table->string('scope_description');
            $this->status($table, 'responsible_party_role');
            $table->text('note')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['proposal_version_id', 'scope_code'], 'uk_rmi_version_scope');
            $table->foreign('proposal_version_id', 'fk_rmi_version')
                ->references('id')->on('proposal_versions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('responsibility_matrix_items', 'responsible_party_role', ['konelsis', 'customer', 'subcontractor', 'supplier', 'shared']);

        Schema::create('estimate_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_version_id');
            $table->unsignedInteger('version_no');
            $this->asciiChar($table, 'currency_code', 3);
            $table->json('exchange_rate_snapshot')->nullable();
            $table->decimal('total_cost', 20, 4)->nullable();
            $table->decimal('total_price', 20, 4)->nullable();
            $table->decimal('target_margin_pct', 7, 4)->nullable();
            $this->status($table)->default('draft');
            $table->unsignedBigInteger('prepared_by_personnel_id');
            $table->unsignedBigInteger('approved_by_personnel_id')->nullable();
            $this->ts($table, 'approved_at')->nullable();
            $table->text('notes')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['proposal_version_id', 'version_no'], 'uk_estimate_versions_proposal_version_no');
            $table->foreign('proposal_version_id', 'fk_estimate_versions_proposal_version')
                ->references('id')->on('proposal_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_estimate_versions_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('prepared_by_personnel_id', 'fk_estimate_versions_preparer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approved_by_personnel_id', 'fk_estimate_versions_approver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('estimate_versions', 'status', ['draft', 'reviewed', 'approved', 'superseded']);
        $this->check('estimate_versions', 'ck_estimate_versions_pct_range', '`target_margin_pct` IS NULL OR (`target_margin_pct` >= 0 AND `target_margin_pct` <= 100)');

        Schema::create('estimate_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('estimate_version_id');
            $table->unsignedBigInteger('parent_line_id')->nullable();
            $this->code($table, 'line_code', 32);
            $this->status($table, 'cost_type');
            $table->string('description');
            $table->decimal('quantity', 18, 6);
            $table->unsignedBigInteger('uom_id');
            $table->decimal('unit_cost', 20, 4);
            $table->decimal('unit_price', 20, 4)->nullable();

            if ($this->isMySql()) {
                $table->decimal('line_total_cost', 20, 4)->storedAs('`quantity` * `unit_cost`');
            } else {
                $table->decimal('line_total_cost', 20, 4)->nullable();
            }

            $this->code($table, 'wbs_hint', 32)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['estimate_version_id', 'line_code'], 'uk_estimate_lines_version_code');
            $table->foreign('estimate_version_id', 'fk_estimate_lines_version')
                ->references('id')->on('estimate_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('parent_line_id', 'fk_estimate_lines_parent')
                ->references('id')->on('estimate_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('uom_id', 'fk_estimate_lines_uom')
                ->references('id')->on('units_of_measure')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('estimate_lines', 'cost_type', [
            'material', 'labor', 'subcontract', 'logistics', 'engineering', 'commissioning', 'overhead', 'contingency', 'other',
        ]);
        $this->check('estimate_lines', 'ck_estimate_lines_quantity_positive', '`quantity` > 0');

        Schema::create('pricing_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('estimate_version_id');
            $this->code($table, 'scenario_code', 32);
            $table->string('name');
            $table->decimal('target_margin_pct', 7, 4);
            $table->decimal('adjustment_pct', 7, 4)->nullable();
            $table->decimal('total_price', 20, 4);
            $table->boolean('is_selected')->default(false);

            if ($this->isMySql()) {
                $table->unsignedBigInteger('selected_guard')
                    ->storedAs('CASE WHEN `is_selected` = 1 THEN `estimate_version_id` END')
                    ->nullable();
            } else {
                $table->unsignedBigInteger('selected_guard')->nullable();
            }

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['estimate_version_id', 'scenario_code'], 'uk_pricing_scenarios_version_code');
            $table->unique('selected_guard', 'uk_pricing_scenarios_selected_guard');
            $table->foreign('estimate_version_id', 'fk_pricing_scenarios_version')
                ->references('id')->on('estimate_versions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->check('pricing_scenarios', 'ck_pricing_scenarios_pct_range', '`target_margin_pct` >= 0 AND `target_margin_pct` <= 100');

        Schema::create('boq_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('estimate_version_id');
            $this->code($table, 'item_code', 32);
            $table->string('description');
            $table->decimal('quantity', 18, 6);
            $table->unsignedBigInteger('uom_id');
            $table->decimal('unit_price', 20, 4)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['estimate_version_id', 'item_code'], 'uk_boq_items_version_code');
            $table->foreign('estimate_version_id', 'fk_boq_items_version')
                ->references('id')->on('estimate_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('uom_id', 'fk_boq_items_uom')
                ->references('id')->on('units_of_measure')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->check('boq_items', 'ck_boq_items_quantity_positive', '`quantity` > 0');
    }

    private function createContractTables(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id');
            $this->code($table, 'contract_no');
            $this->status($table, 'contract_type')->default('contract');
            $table->unsignedBigInteger('customer_party_id');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $this->status($table)->default('draft');
            $table->date('signed_on')->nullable();
            $table->date('effective_from')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('contract_no', 'uk_contracts_contract_no');
            $table->foreign('business_case_id', 'fk_contracts_business_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('customer_party_id', 'fk_contracts_customer_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('contracts', 'contract_type', ['contract', 'loi', 'ntp', 'framework', 'amendment']);
        $this->enumCheck('contracts', 'status', ['draft', 'negotiation', 'signed', 'active', 'completed', 'terminated', 'cancelled']);

        Schema::create('contract_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedInteger('version_no');
            $this->ascii($table, 'locale', 10)->default('tr');
            $this->asciiChar($table, 'currency_code', 3);
            $table->decimal('contract_value', 20, 4)->nullable();
            $this->ascii($table, 'version_hash', 64)->nullable();
            $table->text('summary')->nullable();
            $this->status($table)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->unsignedBigInteger('approved_by_personnel_id')->nullable();
            $this->ts($table, 'approved_at')->nullable();
            $this->ts($table, 'executed_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['contract_id', 'version_no'], 'uk_contract_versions_contract_version_no');
            $table->unique(['contract_id', 'id'], 'uk_contract_versions_contract_id');
            $table->foreign('contract_id', 'fk_contract_versions_contract')
                ->references('id')->on('contracts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_contract_versions_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('approved_by_personnel_id', 'fk_contract_versions_approver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('contract_versions', 'status', ['draft', 'review', 'approved', 'executed', 'superseded']);
        $this->check('contract_versions', 'ck_contract_versions_date_order', '`effective_until` IS NULL OR `effective_from` IS NULL OR `effective_until` >= `effective_from`');

        Schema::create('contract_parties', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contract_version_id');
            $table->unsignedBigInteger('party_id');
            $this->status($table, 'contract_role');
            $table->string('signatory_name')->nullable();
            $this->auditCreated($table);

            $table->unique(['contract_version_id', 'party_id', 'contract_role'], 'uk_contract_parties_version_party_role');
            $table->foreign('contract_version_id', 'fk_contract_parties_version')
                ->references('id')->on('contract_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('party_id', 'fk_contract_parties_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('contract_parties', 'contract_role', ['employer', 'contractor', 'consultant', 'guarantor', 'subcontractor', 'financier']);

        Schema::create('contract_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contract_version_id');
            $table->unsignedBigInteger('document_revision_id');
            $this->status($table, 'document_role');
            $this->auditCreated($table);

            $table->unique(['contract_version_id', 'document_revision_id', 'document_role'], 'uk_contract_documents_version_revision_role');
            $table->foreign('contract_version_id', 'fk_contract_documents_version')
                ->references('id')->on('contract_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_contract_documents_document_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('contract_documents', 'document_role', ['signed_contract', 'annex', 'specification', 'schedule', 'bond', 'insurance', 'other']);

        Schema::create('contract_obligations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contract_version_id');
            $this->code($table, 'obligation_code', 32);
            $this->status($table, 'obligation_type');
            $table->text('description');
            $table->unsignedBigInteger('responsible_party_id')->nullable();
            $table->date('due_on')->nullable();
            $this->status($table)->default('open');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['contract_version_id', 'obligation_code'], 'uk_contract_obligations_version_code');
            $table->foreign('contract_version_id', 'fk_contract_obligations_version')
                ->references('id')->on('contract_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('responsible_party_id', 'fk_contract_obligations_responsible_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('contract_obligations', 'obligation_type', ['delivery', 'payment', 'insurance', 'bond', 'reporting', 'warranty', 'hse', 'legal']);
        $this->enumCheck('contract_obligations', 'status', ['open', 'met', 'breached', 'waived']);

        Schema::create('contract_milestones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contract_version_id');
            $this->code($table, 'milestone_code', 32);
            $table->string('name');
            $table->date('planned_on');
            $table->decimal('payment_pct', 7, 4)->nullable();
            $table->decimal('payment_amount', 20, 4)->nullable();
            $table->text('description')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['contract_version_id', 'milestone_code'], 'uk_contract_milestones_version_code');
            $table->foreign('contract_version_id', 'fk_contract_milestones_version')
                ->references('id')->on('contract_versions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->check('contract_milestones', 'ck_contract_milestones_pct_range', '`payment_pct` IS NULL OR (`payment_pct` >= 0 AND `payment_pct` <= 100)');
    }

    private function createHandoffTables(): void
    {
        Schema::create('operation_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_case_id');
            $table->unsignedBigInteger('prepared_by_employee_id');
            $this->status($table)->default('preparing');
            $table->unsignedBigInteger('accepted_version_id')->nullable();
            $table->unsignedBigInteger('accepted_by_personnel_id')->nullable();
            $this->ts($table, 'accepted_at')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('business_case_id', 'uk_operation_handoffs_business_case');
            $table->foreign('business_case_id', 'fk_operation_handoffs_business_case')
                ->references('id')->on('business_cases')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('prepared_by_employee_id', 'fk_operation_handoffs_preparer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('accepted_by_personnel_id', 'fk_operation_handoffs_acceptor')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('operation_handoffs', 'status', ['preparing', 'in_review', 'accepted', 'rejected', 'cancelled']);

        Schema::create('operation_handoff_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_handoff_id');
            $table->unsignedInteger('version_no');
            $table->unsignedBigInteger('proposal_version_id');
            $table->unsignedBigInteger('contract_version_id')->nullable();
            $table->json('baseline_snapshot');
            $this->ascii($table, 'snapshot_hash', 64);
            $table->unsignedBigInteger('manifest_document_revision_id')->nullable();
            $this->status($table)->default('draft');
            $table->unsignedBigInteger('submitted_by_personnel_id')->nullable();
            $this->ts($table, 'submitted_at')->nullable();
            $table->text('decision_reason')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['operation_handoff_id', 'version_no'], 'uk_ohv_handoff_version_no');
            $table->unique(['operation_handoff_id', 'id'], 'uk_ohv_handoff_id');
            $table->foreign('operation_handoff_id', 'fk_ohv_handoff')
                ->references('id')->on('operation_handoffs')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('proposal_version_id', 'fk_ohv_proposal_version')
                ->references('id')->on('proposal_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('contract_version_id', 'fk_ohv_contract_version')
                ->references('id')->on('contract_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('manifest_document_revision_id', 'fk_ohv_manifest_document_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('submitted_by_personnel_id', 'fk_ohv_submitter')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('operation_handoff_versions', 'status', ['draft', 'submitted', 'accepted', 'rejected', 'superseded']);

        Schema::create('handoff_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_version_id');
            $this->code($table, 'item_code', 32);
            $this->status($table, 'item_type');
            $table->text('description');
            $table->unsignedBigInteger('document_revision_id')->nullable();
            $this->status($table, 'completion_state')->default('pending');
            $table->unsignedBigInteger('waived_by_personnel_id')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['handoff_version_id', 'item_code'], 'uk_handoff_items_version_code');
            $table->foreign('handoff_version_id', 'fk_handoff_items_version')
                ->references('id')->on('operation_handoff_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_handoff_items_document_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('waived_by_personnel_id', 'fk_handoff_items_waiver')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('handoff_items', 'item_type', ['document', 'baseline', 'assumption', 'risk', 'open_issue', 'checklist']);
        $this->enumCheck('handoff_items', 'completion_state', ['pending', 'complete', 'waived', 'not_applicable']);

        Schema::create('handoff_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_version_id');
            $table->unsignedBigInteger('reviewer_personnel_id');
            $this->status($table, 'decision');
            $table->text('comment')->nullable();
            $this->ts($table, 'decided_at');
            $this->auditCreated($table);

            $table->index(['handoff_version_id', 'decided_at'], 'ix_handoff_reviews_version_decided');
            $table->foreign('handoff_version_id', 'fk_handoff_reviews_version')
                ->references('id')->on('operation_handoff_versions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('reviewer_personnel_id', 'fk_handoff_reviews_reviewer')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('handoff_reviews', 'decision', ['accepted', 'rejected', 'returned']);
    }

    /**
     * Karsilikli FK'lar (kok current/accepted surum) ve DMS'in ertelenmis
     * `transmittals.recipient_party_id` kolonu.
     */
    private function addCrossReferences(): void
    {
        Schema::table('tender_notices', function (Blueprint $table): void {
            $table->foreign(['id', 'current_version_id'], 'fk_tender_notices_current_version_agg')
                ->references(['tender_notice_id', 'id'])->on('tender_notice_versions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('proposals', function (Blueprint $table): void {
            $table->foreign(['id', 'current_version_id'], 'fk_proposals_current_version_agg')
                ->references(['proposal_id', 'id'])->on('proposal_versions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreign(['id', 'current_version_id'], 'fk_contracts_current_version_agg')
                ->references(['contract_id', 'id'])->on('contract_versions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('operation_handoffs', function (Blueprint $table): void {
            $table->foreign(['id', 'accepted_version_id'], 'fk_operation_handoffs_accepted_version_agg')
                ->references(['operation_handoff_id', 'id'])->on('operation_handoff_versions')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('transmittals', function (Blueprint $table): void {
            $table->unsignedBigInteger('recipient_party_id')->nullable()->after('recipient_description');
            $table->foreign('recipient_party_id', 'fk_transmittals_recipient_party')
                ->references('id')->on('parties')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('transmittals', function (Blueprint $table): void {
            $table->dropForeign('fk_transmittals_recipient_party');
            $table->dropColumn('recipient_party_id');
        });

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

        Schema::table('operation_handoffs', fn (Blueprint $table) => $table->dropForeign('fk_operation_handoffs_accepted_version_agg'));
        Schema::table('contracts', fn (Blueprint $table) => $table->dropForeign('fk_contracts_current_version_agg'));
        Schema::table('proposals', fn (Blueprint $table) => $table->dropForeign('fk_proposals_current_version_agg'));
        Schema::table('tender_notices', fn (Blueprint $table) => $table->dropForeign('fk_tender_notices_current_version_agg'));

        foreach (array_reverse(self::AUDITED_TABLES) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
