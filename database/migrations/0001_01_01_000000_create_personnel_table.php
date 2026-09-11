<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B00 - Personel tablosu ve oturum altyapisi.
 *
 * Personel hem calisan karti hem giris hesabidir. Kimlik klasik
 * AUTO_INCREMENT id'dir. Organizasyon birimi foreign key'i B03'te eklenir.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('personnel', function (Blueprint $table): void {
            $table->id();

            // Kimlik ve giris
            $table->string('full_name');
            $table->string('email', 320);
            $table->string('normalized_email', 320);
            $this->ts($table, 'email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();

            // Personel karti
            $this->code($table, 'personnel_no', 32)->nullable();
            $table->string('national_id', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('job_title')->nullable();
            $table->unsignedBigInteger('org_unit_id')->nullable();
            $this->ascii($table, 'photo_path', 255)->nullable();
            $table->date('hired_on')->nullable();

            // Tercihler ve durum
            $this->ascii($table, 'locale', 10)->default('tr');
            $this->ascii($table, 'timezone', 64)->default('Europe/Istanbul');
            $this->status($table)->default('invited');
            $this->ts($table, 'password_changed_at')->nullable();
            $this->ts($table, 'last_login_at')->nullable();
            $table->unsignedTinyInteger('failed_login_count')->default(0);

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('normalized_email', 'uk_personnel_normalized_email');
            $table->unique('personnel_no', 'uk_personnel_no');
            $table->unique('national_id', 'uk_personnel_national_id');
            $table->index(['status', 'full_name'], 'ix_personnel_status_name');
            $table->index('org_unit_id', 'ix_personnel_org_unit');
        });

        $this->enumCheck('personnel', 'status', ['invited', 'active', 'on_leave', 'suspended', 'separated']);
        $this->enumCheck('personnel', 'locale', ['tr', 'en']);

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email', 320)->primary();
            $table->string('token');
            $this->ts($table, 'created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            // Laravel oturum surucusu bu kolon adini sabit yazar; personel id'sini tasir.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');

            $table->index('user_id', 'ix_sessions_personnel');
            $table->index('last_activity', 'ix_sessions_last_activity');
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('personnel');
    }
};
