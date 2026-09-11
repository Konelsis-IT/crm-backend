<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B01 - Referans ve organizasyon ana verisi.
 * Personel foreign key'leri B02'de eklenir.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $this->asciiChar($table, 'code', 2)->primary();
            $this->asciiChar($table, 'iso3_code', 3);
            $table->string('name_tr');
            $table->string('name_en');
            $this->ascii($table, 'default_timezone', 64)->nullable();
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('iso3_code', 'uk_countries_iso3_code');
        });
        $this->enumCheck('countries', 'status', ['active', 'inactive']);

        Schema::create('currencies', function (Blueprint $table): void {
            $this->asciiChar($table, 'code', 3)->primary();
            $table->string('name_tr');
            $table->string('name_en');
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);
        });
        $this->enumCheck('currencies', 'status', ['active', 'inactive']);
        $this->check('currencies', 'ck_currencies_decimal_places', '`decimal_places` <= 6');

        Schema::create('units_of_measure', function (Blueprint $table): void {
            $table->id();
            $this->status($table, 'dimension');
            $this->code($table, 'code', 32);
            $table->string('symbol', 32);
            $table->string('name_tr');
            $table->string('name_en');
            $table->unsignedBigInteger('base_unit_id')->nullable();
            $table->decimal('to_base_factor', 24, 12)->nullable();
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['dimension', 'code'], 'uk_units_of_measure_dimension_code');
            $table->foreign('base_unit_id', 'fk_units_of_measure_base_unit')
                ->references('id')->on('units_of_measure')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('units_of_measure', 'dimension', [
            'count', 'length', 'area', 'volume', 'mass', 'time', 'energy', 'power', 'voltage', 'current', 'temperature', 'other',
        ]);
        $this->enumCheck('units_of_measure', 'status', ['active', 'inactive']);
        $this->check('units_of_measure', 'ck_units_of_measure_factor', '`to_base_factor` IS NULL OR `to_base_factor` > 0');
        // Bir birimin kendini taban birim gostermesi uygulama katmaninda engellenir:
        // MySQL 8, CHECK ifadesinde AUTO_INCREMENT kolonuna (id) atif yapilmasina izin vermez.

        Schema::create('security_classifications', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->unsignedTinyInteger('rank');
            $table->string('name_tr');
            $table->string('name_en');
            $table->text('description')->nullable();
            $table->boolean('external_analysis_allowed')->default(false);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_security_classifications_code');
            $table->unique('rank', 'uk_security_classifications_rank');
        });
        $this->enumCheck('security_classifications', 'code', ['public', 'internal', 'confidential', 'restricted']);
        $this->enumCheck('security_classifications', 'status', ['active', 'inactive']);

        Schema::create('retention_policies', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code');
            $table->string('name_tr');
            $table->string('name_en');
            $table->integer('retention_days')->nullable();
            $this->status($table, 'trigger_kind');
            $this->status($table, 'disposition');
            $table->text('legal_basis')->nullable();
            $this->status($table)->default('inactive');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_retention_policies_code');
        });
        $this->enumCheck('retention_policies', 'trigger_kind', ['created_at', 'closed_at', 'archived_at', 'separation', 'project_close', 'manual']);
        $this->enumCheck('retention_policies', 'disposition', ['purge', 'anonymize', 'cold_archive', 'review']);
        $this->enumCheck('retention_policies', 'status', ['active', 'inactive']);

        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code');
            $table->string('name_tr');
            $table->string('name_en');
            $this->ascii($table, 'default_locale', 10)->default('tr');
            $this->ascii($table, 'default_timezone', 64)->default('Europe/Istanbul');
            $this->asciiChar($table, 'default_currency_code', 3);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_organizations_code');
            $table->foreign('default_currency_code', 'fk_organizations_default_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('organizations', 'status', ['active', 'inactive']);

        Schema::create('legal_entities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $this->code($table, 'code');
            $table->string('legal_name');
            $table->string('short_name', 100);
            $this->asciiChar($table, 'country_code', 2);
            $this->asciiChar($table, 'currency_code', 3);
            $this->ascii($table, 'timezone', 64);
            $table->string('tax_number', 32)->nullable();
            $table->string('registration_number', 64)->nullable();
            $this->status($table, 'entity_kind')->default('parent');
            $this->status($table)->default('active');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);
            $this->auditArchived($table);

            $table->unique(['organization_id', 'code'], 'uk_legal_entities_org_code');
            $table->foreign('organization_id', 'fk_legal_entities_organization')
                ->references('id')->on('organizations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('country_code', 'fk_legal_entities_country')
                ->references('code')->on('countries')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('currency_code', 'fk_legal_entities_currency')
                ->references('code')->on('currencies')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('legal_entities', 'entity_kind', ['parent', 'subsidiary', 'branch', 'spv', 'jv']);
        $this->enumCheck('legal_entities', 'status', ['active', 'inactive', 'dissolved']);
        $this->validRangeCheck('legal_entities');

        Schema::create('business_calendars', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code');
            $table->string('name_tr');
            $table->string('name_en');
            $table->unsignedBigInteger('legal_entity_id')->nullable();
            $this->asciiChar($table, 'country_code', 2)->nullable();
            $this->ascii($table, 'timezone', 64);
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('default_guard')->nullable()
                ->storedAs('CASE WHEN is_default = 1 THEN legal_entity_id ELSE NULL END');
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_business_calendars_code');
            $table->unique('default_guard', 'uk_business_calendars_default_guard');
            $table->foreign('legal_entity_id', 'fk_business_calendars_legal_entity')
                ->references('id')->on('legal_entities')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('country_code', 'fk_business_calendars_country')
                ->references('code')->on('countries')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('business_calendars', 'status', ['active', 'inactive']);

        Schema::create('business_calendar_weekdays', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_calendar_id');
            $table->unsignedTinyInteger('iso_weekday');
            $table->time('work_start');
            $table->time('work_end');
            $this->auditCreated($table);

            $table->unique(['business_calendar_id', 'iso_weekday'], 'uk_calendar_weekdays_day');
            $table->foreign('business_calendar_id', 'fk_calendar_weekdays_calendar')
                ->references('id')->on('business_calendars')->cascadeOnDelete()->restrictOnUpdate();
        });
        $this->check('business_calendar_weekdays', 'ck_calendar_weekdays_iso', '`iso_weekday` BETWEEN 1 AND 7');
        $this->check('business_calendar_weekdays', 'ck_calendar_weekdays_hours', '`work_end` > `work_start`');

        Schema::create('calendar_days', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_calendar_id');
            $table->date('local_date');
            $this->status($table, 'day_kind');
            $table->boolean('is_working_day')->default(false);
            $table->string('name_tr')->nullable();
            $table->string('name_en')->nullable();
            $table->string('source_reference', 100)->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['business_calendar_id', 'local_date'], 'uk_calendar_days_calendar_date');
            $table->foreign('business_calendar_id', 'fk_calendar_days_calendar')
                ->references('id')->on('business_calendars')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('calendar_days', 'day_kind', ['public_holiday', 'religious_holiday', 'company_holiday', 'half_day', 'working_override']);

        Schema::create('business_number_allocations', function (Blueprint $table): void {
            $table->id('sequence_no');
            $this->status($table, 'purpose')->default('business_case');
            $this->status($table)->default('assigned');
            $this->ts($table, 'allocated_at');
            $table->unsignedBigInteger('allocated_by_personnel_id')->nullable();
        });
        $this->enumCheck('business_number_allocations', 'purpose', ['business_case']);
        $this->enumCheck('business_number_allocations', 'status', ['assigned', 'void']);
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::dropIfExists('business_number_allocations');
        Schema::dropIfExists('calendar_days');
        Schema::dropIfExists('business_calendar_weekdays');
        Schema::dropIfExists('business_calendars');
        Schema::dropIfExists('legal_entities');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('security_classifications');
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('countries');
    }
};
