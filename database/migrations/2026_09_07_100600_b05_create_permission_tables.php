<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B05 - RBAC (karar D-16): spatie/laravel-permission semasi.
 *
 * Tablo adlari (roles, permissions, model_has_roles, model_has_permissions,
 * role_has_permissions) 06 SS3.6'da ayrilmis isimlerdir; config/permission.php
 * varsayilanlariyla birebir eslesir (teams=false). model_morph_key BIGINT
 * UNSIGNED'dir (D-16), personnel.id ile ayni tip.
 *
 * Bu tablolar paketin kendi Eloquent modelleri (Spatie\Permission\Models\*)
 * tarafindan yonetilir; App\Services\AbstractService/HasAuditColumns
 * deseni buraya uygulanmaz (paketin kendi yasam dongusu var).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name'], 'uk_permissions_name_guard');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name'], 'uk_roles_name_guard');
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'ix_model_has_permissions_model');
            $table->foreign('permission_id', 'fk_model_has_permissions_permission')
                ->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type'], 'pk_model_has_permissions');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'ix_model_has_roles_model');
            $table->foreign('role_id', 'fk_model_has_roles_role')
                ->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type'], 'pk_model_has_roles');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id', 'fk_role_has_permissions_permission')
                ->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id', 'fk_role_has_permissions_role')
                ->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'pk_role_has_permissions');
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
