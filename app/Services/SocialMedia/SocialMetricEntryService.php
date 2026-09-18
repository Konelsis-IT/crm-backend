<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Enums\SocialMedia\SocialMetricSource;
use App\Enums\SocialMedia\SocialPlatform;
use App\Exceptions\DuplicateRecordException;
use App\Exceptions\SocialMedia\FileTooLargeException;
use App\Exceptions\SocialMedia\UnsupportedMediaException;
use App\Models\Document\FileObject;
use App\Models\SocialMedia\SocialMetricEntry;
use App\Models\SocialMedia\SocialProfile;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Document\FileObjectService;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use BackedEnum;
use DateTimeInterface;
use finfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Platform istatistigi servisi (B31, D-106): donemlik olcumler elle girilir
 * ve / veya yuklenen rapor dosyasiyla belgelenir.
 *
 * - Hesap + platform + donem tektir (uk_social_metric_entries_period); ikinci
 *   giris DuplicateRecordException ile reddedilir.
 * - Rapor dosyasi `report_temp_path` (local diskte ANAHTAR, E9) +
 *   `report_original_name` ile gelir. Kabul kurali (E15): istemci uzantisi
 *   report_extensions icinde VE finfo turu report_mimes icinde olmali; belirsiz
 *   turler (octet-stream, zip, CDFV2, duz metin) yalniz ilgili tablo
 *   uzantilariyla kabul edilir; boyut max_report_kb'yi asamaz. Dosya
 *   FileObjectService::createFromUpload ile saklanir (sha256 tekillestirme);
 *   eski rapor dosyasi silinmez, yalniz baglanti degisir.
 * - `source`: rapor dosyasi varsa "upload", yoksa "manual".
 * - `remove_report` dolu gelirse rapor baglantisi kaldirilir.
 *
 * Giris silinmez; yanlis giris duzeltilir.
 */
final class SocialMetricEntryService extends AbstractService
{
    protected string $model = SocialMetricEntry::class;

    protected string $orderBy = 'period_end_on';

    protected string $orderDirection = 'desc';

    /** @var list<string> */
    protected array $with = ['createdBy.orgUnit', 'reportFile'];

    /**
     * Icerigi tek basina turu kanitlamayan finfo sonuclari => kabul edildigi uzantilar.
     *
     * @var array<string, list<string>>
     */
    private const LOOSE_MIMES = [
        'application/octet-stream' => ['xls', 'xlsx'],
        'application/zip' => ['xlsx'],
        'application/cdfv2' => ['xls'],
        'text/plain' => ['csv'],
    ];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly FileObjectService $files,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        [$tempKey, $originalName] = $this->reportInput($data);
        $data = $this->normalize($data, null);

        if ($tempKey !== null) {
            $this->assertReportAcceptable($tempKey, $originalName);
        }

        try {
            return $this->transactions->run(function () use ($data, $tempKey, $originalName): Model {
                $this->assertUniquePeriod($data, null);

                $file = $tempKey !== null ? $this->files->createFromUpload($tempKey, $originalName) : null;

                $data['file_object_id'] = $file?->getKey();
                $data['source'] = ($file !== null ? SocialMetricSource::Upload : SocialMetricSource::Manual)->value;

                return parent::create($data)->load($this->with);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw DuplicateRecordException::make([], $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        [$tempKey, $originalName] = $this->reportInput($data);
        $removeReport = $tempKey === null && filter_var($data['remove_report'] ?? false, FILTER_VALIDATE_BOOL);

        if ($tempKey !== null) {
            $this->assertReportAcceptable($tempKey, $originalName);
        }

        try {
            return $this->transactions->run(function () use ($record, $data, $tempKey, $originalName, $removeReport): Model {
                /** @var SocialMetricEntry $entry */
                $entry = $this->lockForUpdate($record);
                $data = $this->normalize($data, $entry);

                $this->assertUniquePeriod($data + [
                    'profile_id' => $entry->profile_id,
                    'platform' => $entry->platform instanceof SocialPlatform ? $entry->platform->value : $entry->platform,
                    'period_start_on' => $entry->period_start_on?->format('Y-m-d'),
                    'period_end_on' => $entry->period_end_on?->format('Y-m-d'),
                ], (int) $entry->getKey());

                if ($tempKey !== null) {
                    $data['file_object_id'] = $this->files->createFromUpload($tempKey, $originalName)->getKey();
                    $data['source'] = SocialMetricSource::Upload->value;
                } elseif ($removeReport) {
                    $data['file_object_id'] = null;
                    $data['source'] = SocialMetricSource::Manual->value;
                }

                $entry->fill($this->prepare($data, $entry));
                $changes = $this->saveWithoutVersion($entry);

                if ($changes !== []) {
                    $this->recordActivity($entry, 'updated', $this->labelled($changes));
                }

                return $entry->load($this->with);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw DuplicateRecordException::make([], $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version'], $data['report_temp_path'], $data['report_original_name'], $data['remove_report']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return array_filter([
            'hesap' => $this->display('profile_id', $record->getAttribute('profile_id')),
            'platform' => $this->display('platform', $record->getAttribute('platform')),
            'donem' => $this->display('period_start_on', $record->getAttribute('period_start_on'))
                .' – '.$this->display('period_end_on', $record->getAttribute('period_end_on')),
            'dosya' => $this->display('file_object_id', $record->getAttribute('file_object_id')),
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * Rapor dosyasi girdisi: [local disk anahtari, ozgun ad]; dosya yoksa [null, null].
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: ?string}
     */
    private function reportInput(array $data): array
    {
        $tempKey = $data['report_temp_path'] ?? null;

        if (! is_string($tempKey) || trim($tempKey) === '') {
            return [null, null];
        }

        $name = $data['report_original_name'] ?? null;

        return [$tempKey, is_string($name) && trim($name) !== '' ? trim($name) : basename($tempKey)];
    }

    /**
     * Gelen veriyi kolonlara indirger. Guncellemede gonderilmeyen alan oldugu
     * gibi kalir; bos gonderilen olcum ve not temizlenir (null = girilmedi).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?SocialMetricEntry $record): array
    {
        $clean = [];

        if (filled($data['profile_id'] ?? null)) {
            $clean['profile_id'] = (int) $data['profile_id'];
        }

        if (filled($data['platform'] ?? null)) {
            $platform = $data['platform'] instanceof SocialPlatform ? $data['platform'] : SocialPlatform::tryFrom((string) $data['platform']);

            if ($platform !== null) {
                $clean['platform'] = $platform->value;
            }
        }

        foreach (['period_start_on', 'period_end_on'] as $key) {
            if (filled($data[$key] ?? null)) {
                $clean[$key] = $data[$key] instanceof DateTimeInterface
                    ? $data[$key]->format('Y-m-d')
                    : substr(trim((string) $data[$key]), 0, 10);
            }
        }

        foreach (SocialMetricEntry::METRICS as $metric) {
            if (array_key_exists($metric, $data)) {
                $clean[$metric] = filled($data[$metric]) ? max(0, (int) $data[$metric]) : null;
            }
        }

        if (array_key_exists('note', $data)) {
            $note = trim((string) ($data['note'] ?? ''));
            $clean['note'] = $note === '' ? null : $note;
        }

        if ($record === null) {
            foreach (['profile_id', 'platform', 'period_start_on', 'period_end_on'] as $required) {
                if (! isset($clean[$required])) {
                    throw new InvalidArgumentException('Istatistik girisinde zorunlu alan eksik: '.$required);
                }
            }
        }

        $start = $clean['period_start_on'] ?? $record?->period_start_on?->format('Y-m-d');
        $end = $clean['period_end_on'] ?? $record?->period_end_on?->format('Y-m-d');

        if ($start !== null && $end !== null && $end < $start) {
            throw new InvalidArgumentException('Donem bitisi baslangictan once olamaz; denetleyici dogrulamasi atlanmis.');
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertUniquePeriod(array $data, ?int $exceptId): void
    {
        $exists = SocialMetricEntry::query()
            ->where('profile_id', (int) ($data['profile_id'] ?? 0))
            ->where('platform', (string) ($data['platform'] ?? ''))
            ->where('period_start_on', (string) ($data['period_start_on'] ?? ''))
            ->where('period_end_on', (string) ($data['period_end_on'] ?? ''))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw DuplicateRecordException::make();
        }
    }

    /**
     * Rapor dosyasi kabul kurali (E15). Dosya saklanmadan ONCE calisir; boylece
     * reddedilen dosya kalici klasore hic tasinmaz.
     */
    private function assertReportAcceptable(string $tempKey, ?string $originalName): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($tempKey)) {
            throw UnsupportedMediaException::make();
        }

        $size = (int) $disk->size($tempKey);

        if ($size <= 0) {
            throw UnsupportedMediaException::make();
        }

        $maxKb = max(1, (int) config('konelsis.social_media.max_report_kb', 20480));

        if ($size > $maxKb * 1024) {
            throw FileTooLargeException::make(['max' => $this->readableKb($maxKb)]);
        }

        $extension = strtolower((string) pathinfo((string) $originalName, PATHINFO_EXTENSION));
        $extensions = array_map('strtolower', (array) config('konelsis.social_media.report_extensions', []));

        if ($extension === '' || ! in_array($extension, $extensions, true)) {
            throw UnsupportedMediaException::make();
        }

        $mime = $this->detectMime($disk->path($tempKey));
        $mimes = array_map('strtolower', (array) config('konelsis.social_media.report_mimes', []));

        if ($mime === null || ! in_array($mime, $mimes, true)) {
            throw UnsupportedMediaException::make();
        }

        if (isset(self::LOOSE_MIMES[$mime]) && ! in_array($extension, self::LOOSE_MIMES[$mime], true)) {
            throw UnsupportedMediaException::make();
        }
    }

    /** Dosya iceriginden tur (finfo); okunamazsa null. */
    private function detectMime(string $absolutePath): ?string
    {
        if (! class_exists(finfo::class) || ! is_file($absolutePath)) {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath);

        return is_string($mime) && $mime !== '' ? strtolower($mime) : null;
    }

    private function readableKb(int $kilobytes): string
    {
        return $kilobytes >= 1024
            ? number_format($kilobytes / 1024, 0, ',', '.').' MB'
            : number_format($kilobytes, 0, ',', '.').' KB';
    }

    /**
     * @param  array<string, array{onceki: mixed, yeni: mixed}>  $changes
     * @return array<string, array{onceki: mixed, yeni: mixed}>
     */
    private function labelled(array $changes): array
    {
        foreach ($changes as $column => $pair) {
            $changes[$column] = [
                'onceki' => $this->display((string) $column, $pair['onceki'] ?? null),
                'yeni' => $this->display((string) $column, $pair['yeni'] ?? null),
            ];
        }

        return $changes;
    }

    /**
     * Hareket kaydinda gosterilecek deger: platform ve kaynak etiketle, tarih
     * gun.ay.yil olarak, hesap ve dosya adiyla yazilir (kimlik yazilmaz).
     */
    private function display(string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (true) {
            $value instanceof SocialPlatform, $value instanceof SocialMetricSource => (string) $value->getLabel(),
            $column === 'platform' => (string) (SocialPlatform::tryFrom((string) $value)?->getLabel() ?? $value),
            $column === 'source' => (string) (SocialMetricSource::tryFrom((string) $value)?->getLabel() ?? $value),
            $value instanceof DateTimeInterface => $value->format('d.m.Y'),
            in_array($column, ['period_start_on', 'period_end_on'], true) => $this->dayMonthYear((string) $value),
            $column === 'profile_id' => (string) (SocialProfile::query()->whereKey((int) $value)->value('name') ?? ''),
            $column === 'file_object_id' => (string) (FileObject::query()->whereKey((int) $value)->value('original_name') ?? ''),
            $value instanceof BackedEnum => $value->value,
            default => $value,
        };
    }

    private function dayMonthYear(string $date): string
    {
        $parts = explode('-', substr($date, 0, 10));

        return count($parts) === 3 ? $parts[2].'.'.$parts[1].'.'.$parts[0] : $date;
    }
}
