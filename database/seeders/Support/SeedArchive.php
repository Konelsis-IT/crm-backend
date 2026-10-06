<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed satir arsivi (B45, D-165). Bir seeder'in bir veri satiri (sabit
 * anahtariyla) islendiginde arsive dusulur; arsivdeki satir bir daha
 * islenmez. Arsiv her ortamin kendi veritabanindadir. B45 uygulanmadiysa
 * arsiv yoktur: satirlar islenir, yalniz koruma (SeedGuard) gecerlidir.
 *
 * Tabloya yalniz eklenir; satir guncellenmez, silinmez.
 */
final class SeedArchive
{
    private static ?self $instance = null;

    private ?bool $ready = null;

    /** @var array<string, array<string, true>> seeder => satir ozeti */
    private array $known = [];

    /**
     * Ara bellek (isaretleme kipi): islem geri alinsa da satirlar kaybolmasin
     * diye once burada toplanir, flush() ile yazilir.
     *
     * @var list<array<string, mixed>>|null
     */
    private ?array $buffer = null;

    public function beginBuffer(): void
    {
        $this->buffer = [];
    }

    /** Ara bellekteki satirlari yazar; yazilan satir sayisini doner. */
    public function flush(): int
    {
        $rows = $this->buffer ?? [];
        $this->buffer = null;

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('seed_archive')->insertOrIgnore($chunk);
        }

        return count($rows);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    public function ready(): bool
    {
        return $this->ready ??= Schema::hasTable('seed_archive');
    }

    public function has(string $seeder, string $key): bool
    {
        if (! $this->ready()) {
            return false;
        }

        return isset($this->load($seeder)[self::hash($key)]);
    }

    public function record(string $seeder, string $key, ?Model $record): void
    {
        if (! $this->ready()) {
            return;
        }

        $hash = self::hash($key);

        if (isset($this->load($seeder)[$hash])) {
            return;
        }

        $row = [
            'seeder' => $seeder,
            'row_hash' => $hash,
            'row_key' => mb_substr($key, 0, 500),
            'record_type' => $record !== null ? $record->getMorphClass() : null,
            'record_id' => $record !== null && is_numeric($record->getKey()) ? (int) $record->getKey() : null,
            'archived_at' => CarbonImmutable::now('UTC'),
        ];

        if ($this->buffer !== null) {
            $this->buffer[] = $row;
        } else {
            DB::table('seed_archive')->insertOrIgnore($row);
        }

        $this->known[$seeder][$hash] = true;
    }

    /**
     * @return array<string, true>
     */
    private function load(string $seeder): array
    {
        if (! isset($this->known[$seeder])) {
            $this->known[$seeder] = DB::table('seed_archive')
                ->where('seeder', $seeder)
                ->pluck('row_hash')
                ->mapWithKeys(static fn (mixed $hash): array => [(string) $hash => true])
                ->all();
        }

        return $this->known[$seeder];
    }

    private static function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
