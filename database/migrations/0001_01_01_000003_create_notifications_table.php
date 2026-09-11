<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B00 - Filament bildirim tablosu.
 *
 * Panel icindeki zil ikonundaki bildirimler bu tabloda tutulur.
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $this->ts($table, 'read_at')->nullable();
            $this->ts($table, 'created_at')->nullable();
            $this->ts($table, 'updated_at')->nullable();

            $table->index(['notifiable_type', 'notifiable_id'], 'ix_notifications_notifiable');
        });
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::dropIfExists('notifications');
    }
};
