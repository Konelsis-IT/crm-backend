<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Konelsis migration'ları için ortak fiziksel kurallar.
 *
 * Kimlikler klasik AUTO_INCREMENT `BIGINT UNSIGNED`'dır (karar D-15R).
 * Migration dosyalarını kodlama ajanları yazar, yalnız yetkili DBA/DevOps
 * süreci uygular.
 */
abstract class KonelsisMigration extends Migration
{
    protected function driver(): string
    {
        return Schema::getConnection()->getDriverName();
    }

    protected function isMySql(): bool
    {
        return in_array($this->driver(), ['mysql', 'mariadb'], true);
    }

    /** ASCII kısa metin (locale, timezone, dosya yolu). */
    protected function ascii(Blueprint $table, string $column, int $length): ColumnDefinition
    {
        $definition = $table->string($column, $length);

        if ($this->isMySql()) {
            $definition->charset('ascii')->collation('ascii_general_ci');
        }

        return $definition;
    }

    /** ASCII sabit uzunluklu kod (ISO ülke/para birimi). */
    protected function asciiChar(Blueprint $table, string $column, int $length): ColumnDefinition
    {
        $definition = $table->char($column, $length);

        if ($this->isMySql()) {
            $definition->charset('ascii')->collation('ascii_general_ci');
        }

        return $definition;
    }

    /** Büyük/küçük harf duyarlı iş kodu. */
    protected function code(Blueprint $table, string $column, int $length = 64): ColumnDefinition
    {
        $definition = $table->string($column, $length);

        if ($this->isMySql()) {
            $definition->collation('utf8mb4_0900_as_cs');
        }

        return $definition;
    }

    /** Enum karşılığı durum kolonu; değerler enumCheck() ile korunur. */
    protected function status(Blueprint $table, string $column = 'status'): ColumnDefinition
    {
        return $table->string($column, 32);
    }

    /** UTC an, DATETIME(6). */
    protected function ts(Blueprint $table, string $column): ColumnDefinition
    {
        return $table->dateTime($column, 6);
    }

    /** Oluşturma izi: created_at + created_by_personnel_id (NULL = sistem). */
    protected function auditCreated(Blueprint $table): void
    {
        $this->ts($table, 'created_at')->useCurrent();
        $table->unsignedBigInteger('created_by_personnel_id')->nullable();
    }

    /** Güncelleme izi: updated_at + updated_by_personnel_id + row_version. */
    protected function auditUpdated(Blueprint $table): void
    {
        $this->ts($table, 'updated_at')->useCurrent()->useCurrentOnUpdate();
        $table->unsignedBigInteger('updated_by_personnel_id')->nullable();
        $table->unsignedBigInteger('row_version')->default(1);
    }

    /** Arşiv izi. */
    protected function auditArchived(Blueprint $table): void
    {
        $this->ts($table, 'archived_at')->nullable();
        $table->unsignedBigInteger('archived_by_personnel_id')->nullable();
        $table->string('archive_reason', 100)->nullable();
    }

    /**
     * Audit kolonlarından personel tablosuna RESTRICT foreign key ekler.
     * Tabloda bulunmayan kolonlar atlanır.
     */
    protected function personnelForeignKeys(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            foreach (['created_by_personnel_id', 'updated_by_personnel_id', 'archived_by_personnel_id'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $blueprint->foreign($column, $this->fkName($table, $column))
                        ->references('id')
                        ->on('personnel')
                        ->restrictOnDelete()
                        ->restrictOnUpdate();
                }
            }
        });
    }

    protected function fkName(string $table, string $column): string
    {
        $columnRoot = preg_replace('/_id$/', '', $column) ?? $column;

        return $this->shorten("fk_{$table}_{$columnRoot}");
    }

    /** MySQL 64 karakter tanımlayıcı sınırını deterministik biçimde korur. */
    protected function shorten(string $name): string
    {
        if (strlen($name) <= 64) {
            return $name;
        }

        return substr($name, 0, 55).'_'.substr(md5($name), 0, 8);
    }

    /** İsimlendirilmiş CHECK (yalnız MySQL; SQLite sonradan constraint eklemez). */
    protected function check(string $table, string $name, string $expression): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` ADD CONSTRAINT `%s` CHECK (%s)', $table, $this->shorten($name), $expression));
    }

    /**
     * @param  list<string>  $values
     */
    protected function enumCheck(string $table, string $column, array $values): void
    {
        $list = implode(', ', array_map(
            static fn (string $value): string => "'".str_replace("'", "''", $value)."'",
            $values,
        ));

        $this->check($table, "ck_{$table}_{$column}_enum", "`{$column}` IN ({$list})");
    }

    protected function validRangeCheck(string $table, string $from = 'valid_from', string $until = 'valid_until'): void
    {
        $this->check($table, "ck_{$table}_valid_range", "`{$until}` IS NULL OR `{$until}` > `{$from}`");
    }

    /**
     * down() adımları kodlama ajanları tarafından hiç çalıştırılmaz ve
     * production'da asla açılmaz; DBA açıkça izin vermelidir.
     */
    protected function assertDestructiveAllowed(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Geri alma adımları production ortamında hiçbir zaman çalıştırılamaz.');
        }

        if (! (bool) config('konelsis.schema_guard.destructive_allowed', false)) {
            throw new RuntimeException('Geri alma adımları KONELSIS_DESTRUCTIVE_SCHEMA_ALLOWED=true gerektirir (yalnız DBA/DevOps).');
        }
    }
}
